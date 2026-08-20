-- StudyFlow database schema
-- Import this file in phpMyAdmin (Import tab) or via:
--   mysql -u root -p < studyflow.sql
-- It creates the `studyflow` database and all tables the app uses.

CREATE DATABASE IF NOT EXISTS studyflow
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE studyflow;

-- ---------------------------------------------------------
-- users
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(30) NULL,
  email VARCHAR(150) NOT NULL,
  password VARCHAR(255) NOT NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- timezone on users (run this ALTER manually if upgrading an existing db)
-- ---------------------------------------------------------
ALTER TABLE users ADD COLUMN IF NOT EXISTS timezone VARCHAR(64) NOT NULL DEFAULT 'UTC';

-- ---------------------------------------------------------
-- subjects
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS subjects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  subject_name VARCHAR(150) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_subjects_user (user_id),
  CONSTRAINT fk_subjects_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- tasks
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NULL,
  task_title VARCHAR(200) NOT NULL,
  due_date DATE NULL,
  status ENUM('pending','completed') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tasks_user (user_id),
  KEY idx_tasks_subject (subject_id),
  CONSTRAINT fk_tasks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tasks_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- user_streaks
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_streaks (
  user_id INT UNSIGNED NOT NULL,
  last_active_date DATE NOT NULL,
  last_active_at DATETIME NOT NULL,
  streak_count INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_streaks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If migrating an existing install, run:
-- ALTER TABLE user_streaks ADD COLUMN last_active_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER last_active_date;

-- ---------------------------------------------------------
-- xp on users (run this ALTER manually if upgrading an existing db)
-- ---------------------------------------------------------
ALTER TABLE users ADD COLUMN IF NOT EXISTS xp INT UNSIGNED NOT NULL DEFAULT 0;

-- ---------------------------------------------------------
-- flashcards (persist per user, survive reload/logout)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS flashcards (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  front VARCHAR(255) NOT NULL,
  back TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_flashcards_user (user_id),
  CONSTRAINT fk_flashcards_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- ai_conversations / ai_messages (persistent AI Tutor chat history,
-- like ChatGPT/Claude's sidebar of past chats)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_conversations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL DEFAULT 'New chat',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_aiconv_user (user_id),
  CONSTRAINT fk_aiconv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id INT UNSIGNED NOT NULL,
  role ENUM('user','ai') NOT NULL,
  content MEDIUMTEXT NOT NULL,
  image_data_url MEDIUMTEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_aimsg_conv (conversation_id),
  CONSTRAINT fk_aimsg_conv FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- community groups (topic-based group chats)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS community_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  icon VARCHAR(10) NOT NULL DEFAULT '💬',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS community_group_members (
  group_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (group_id, user_id),
  CONSTRAINT fk_gmember_group FOREIGN KEY (group_id) REFERENCES community_groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_gmember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS community_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gmsg_group (group_id),
  CONSTRAINT fk_gmsg_group FOREIGN KEY (group_id) REFERENCES community_groups(id) ON DELETE CASCADE,
  CONSTRAINT fk_gmsg_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- subject tag so groups can be found by toggling/searching a subject
ALTER TABLE community_groups ADD COLUMN IF NOT EXISTS subject VARCHAR(150) NOT NULL DEFAULT '';

INSERT IGNORE INTO community_groups (id, name, subject, description, icon) VALUES
  (1, 'Mathematics', 'Mathematics', 'Algebra, calculus, mechanics — ask, answer, compare methods.', '📐'),
  (2, 'Sciences', 'Biology', 'Physics, Chemistry, Biology — concepts, practicals, past questions.', '🧪'),
  (3, 'Past Questions Strategy', 'General', 'How to actually use past questions to study smarter.', '📝'),
  (4, 'Exam Motivation & Focus', 'General', 'Accountability, study streaks, beating burnout together.', '🔥'),
  (5, 'Mass Communication', 'Mass Communication', 'Broadcasting, journalism, PR — coursework and internships.', '🎙️'),
  (6, 'Computer Science & Software Eng.', 'Computer Science', 'Coding, projects, SIWES and job-hunting tips.', '💻'),
  (7, 'English Language', 'English Language', 'Grammar, comprehension, literature, essay writing.', '📖'),
  (8, 'Law', 'Law', 'Case law, moot court, bar prep chat.', '⚖️'),
  (9, 'Nursing & Health Sciences', 'Nursing Science', 'Clinicals, anatomy, board exam prep.', '🏥'),
  (10, 'Business & Accounting', 'Accounting', 'Financial accounting, economics, entrepreneurship.', '💰');

-- ---------------------------------------------------------
-- premium flag on users
-- ---------------------------------------------------------
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_premium TINYINT(1) NOT NULL DEFAULT 0;

-- ---------------------------------------------------------
-- quiz_scores — powers the real (non-random) live leaderboard
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS quiz_scores (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  subject VARCHAR(150) NOT NULL,
  difficulty VARCHAR(20) NOT NULL DEFAULT 'Easy',
  score INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_qs_subject (subject),
  KEY idx_qs_user (user_id),
  CONSTRAINT fk_qs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- flag on community_messages for basic contact-sharing filtering
-- (message text is still stored — flag lets the UI show a notice)
-- ---------------------------------------------------------
ALTER TABLE community_messages ADD COLUMN IF NOT EXISTS was_filtered TINYINT(1) NOT NULL DEFAULT 0;

-- ---------------------------------------------------------
-- timetable_entries (user's own editable weekly timetable)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS timetable_entries (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  day_of_week ENUM('Mon','Tue','Wed','Thu','Fri','Sat','Sun') NOT NULL,
  start_time VARCHAR(10) NOT NULL,
  end_time VARCHAR(10) NOT NULL,
  label VARCHAR(150) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_timetable_user (user_id),
  CONSTRAINT fk_timetable_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- pq_cache — caches AI-generated past questions so the same
-- subject/topic/year/level/university combo is instant on repeat
-- and doesn't burn free-tier Gemini quota every single time.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS pq_cache (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cache_key VARCHAR(255) NOT NULL,
  subject VARCHAR(150) NOT NULL,
  topic VARCHAR(150) NOT NULL DEFAULT '',
  year VARCHAR(10) NOT NULL DEFAULT '',
  level VARCHAR(20) NOT NULL DEFAULT '',
  university VARCHAR(150) NOT NULL DEFAULT '',
  questions_json MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pq_cache_key (cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- quiz_cache — same idea for generated quiz question sets
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS quiz_cache (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cache_key VARCHAR(255) NOT NULL,
  subject VARCHAR(150) NOT NULL,
  difficulty VARCHAR(20) NOT NULL DEFAULT 'Easy',
  count INT UNSIGNED NOT NULL DEFAULT 10,
  questions_json MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_quiz_cache_key (cache_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- username — a unique handle, separate from display name, so
-- friends can be found reliably even when names are common.
-- ---------------------------------------------------------
ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(30) NULL;
ALTER TABLE users ADD UNIQUE KEY uq_users_username (username);
ALTER TABLE users ADD COLUMN IF NOT EXISTS exam_goal VARCHAR(50) NOT NULL DEFAULT '';

-- ---------------------------------------------------------
-- streak shields — earned automatically every 14-day streak,
-- protects one missed 34-hour window instead of resetting to 0.
-- ---------------------------------------------------------
ALTER TABLE user_streaks ADD COLUMN IF NOT EXISTS shields INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE user_streaks ADD COLUMN IF NOT EXISTS longest_streak INT UNSIGNED NOT NULL DEFAULT 0;

-- ---------------------------------------------------------
-- daily_challenges — one lightweight challenge per user per day
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS daily_challenges (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  challenge_date DATE NOT NULL,
  challenge_type VARCHAR(30) NOT NULL,
  target_count INT UNSIGNED NOT NULL DEFAULT 1,
  progress_count INT UNSIGNED NOT NULL DEFAULT 0,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  xp_awarded INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_daily_user_date (user_id, challenge_date),
  CONSTRAINT fk_dc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- coins — a second, cosmetic-flavored currency alongside XP,
-- earned from badges and friend challenges.
-- ---------------------------------------------------------
ALTER TABLE users ADD COLUMN IF NOT EXISTS coins INT UNSIGNED NOT NULL DEFAULT 0;

-- ---------------------------------------------------------
-- user_badges — persists which badges have actually been awarded,
-- so a badge only pays out coins the first time it's earned.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_badges (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  badge_key VARCHAR(50) NOT NULL,
  earned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_badge (user_id, badge_key),
  CONSTRAINT fk_ub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- friendships — one row per pair. status: pending / accepted / blocked.
-- requester_id sent the request (or is the blocker, for 'blocked').
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS friendships (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  requester_id INT UNSIGNED NOT NULL,
  addressee_id INT UNSIGNED NOT NULL,
  status ENUM('pending','accepted','blocked') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_friend_pair (requester_id, addressee_id),
  KEY idx_addressee (addressee_id),
  CONSTRAINT fk_fr_requester FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fr_addressee FOREIGN KEY (addressee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- friend_challenges — a friendly 7-day race on a measurable activity.
-- Progress is computed on the fly from existing tables (quiz_scores,
-- flashcards, tasks) rather than duplicated here.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS friend_challenges (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  creator_id INT UNSIGNED NOT NULL,
  friend_id INT UNSIGNED NOT NULL,
  challenge_type VARCHAR(30) NOT NULL,
  target_count INT UNSIGNED NOT NULL DEFAULT 5,
  status ENUM('pending','active','declined','finished') NOT NULL DEFAULT 'pending',
  starts_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ends_at TIMESTAMP NOT NULL,
  winner_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_fc_creator FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fc_friend FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- friend_duels — a real 1v1 quiz duel with an XP wager. Both
-- players answer the same question set; higher score wins (faster
-- time breaks ties). Winner gains the stake, loser loses it — XP
-- can go negative. Users with XP below 10 can't start or accept
-- duels until they've earned it back through normal quizzes.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS friend_duels (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  challenger_id INT UNSIGNED NOT NULL,
  opponent_id INT UNSIGNED NOT NULL,
  subject VARCHAR(150) NOT NULL,
  difficulty VARCHAR(20) NOT NULL DEFAULT 'Easy',
  question_count INT UNSIGNED NOT NULL DEFAULT 5,
  stake INT NOT NULL DEFAULT 50,
  status ENUM('pending','active','finished','declined') NOT NULL DEFAULT 'pending',
  questions_json MEDIUMTEXT NULL,
  challenger_score INT NULL,
  challenger_time_seconds INT NULL,
  challenger_finished TINYINT(1) NOT NULL DEFAULT 0,
  opponent_score INT NULL,
  opponent_time_seconds INT NULL,
  opponent_finished TINYINT(1) NOT NULL DEFAULT 0,
  winner_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  accepted_at TIMESTAMP NULL,
  resolved_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_fd_challenger FOREIGN KEY (challenger_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_fd_opponent FOREIGN KEY (opponent_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------
-- attachments on group chat messages — images, files, and voice
-- notes recorded in-browser. attachment_type is one of:
-- 'image' | 'audio' | 'file'
-- ---------------------------------------------------------
ALTER TABLE community_messages ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(255) NULL;
ALTER TABLE community_messages ADD COLUMN IF NOT EXISTS attachment_type VARCHAR(10) NULL;
ALTER TABLE community_messages ADD COLUMN IF NOT EXISTS attachment_name VARCHAR(255) NULL;

-- ---------------------------------------------------------
-- direct_messages — private 1:1 texting between accepted friends
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS direct_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sender_id INT UNSIGNED NOT NULL,
  recipient_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_dm_pair (sender_id, recipient_id),
  CONSTRAINT fk_dm_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_dm_recipient FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
