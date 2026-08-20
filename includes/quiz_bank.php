<?php
// Multiple-choice question bank for the Quiz module.
// Each question: q (text), opts (array of 4), correct (index of correct option).
return [
    'Mathematics' => [
        ['q' => 'What is the value of x in 2x + 5 = 15?', 'opts' => ['5','10','7.5','2.5'], 'correct' => 0],
        ['q' => 'What is the derivative of x³?', 'opts' => ['x²','3x²','3x','x⁴/4'], 'correct' => 1],
        ['q' => 'What is the next term in the sequence 2, 4, 8, 16, ...?', 'opts' => ['24','30','32','20'], 'correct' => 2],
        ['q' => 'What is the area of a circle with radius 7 (use π ≈ 22/7)?', 'opts' => ['154','44','22','308'], 'correct' => 0],
        ['q' => 'Simplify: (2³) × (2²)', 'opts' => ['2⁵','2⁶','4⁵','2¹'], 'correct' => 0],
        ['q' => 'What is 15% of 200?', 'opts' => ['15','30','20','25'], 'correct' => 1],
        ['q' => 'The sum of angles in a triangle is:', 'opts' => ['90°','180°','270°','360°'], 'correct' => 1],
        ['q' => 'Solve for x: x² = 49', 'opts' => ['x = 7 only','x = -7 only','x = ±7','x = 14'], 'correct' => 2],
    ],
    'English Language' => [
        ['q' => 'Choose the correctly spelled word.', 'opts' => ['Occassion','Occasion','Ocasion','Occaision'], 'correct' => 1],
        ['q' => '"The wind whispered through the trees" is an example of:', 'opts' => ['Simile','Metaphor','Personification','Hyperbole'], 'correct' => 2],
        ['q' => 'Identify the correct sentence.', 'opts' => ['She don\'t like tea.','She doesn\'t like tea.','She not like tea.','She no like tea.'], 'correct' => 1],
        ['q' => 'A word that means the opposite of another is called a(n):', 'opts' => ['Synonym','Antonym','Homonym','Acronym'], 'correct' => 1],
        ['q' => 'Which is a proper noun?', 'opts' => ['city','river','London','school'], 'correct' => 2],
        ['q' => 'The past tense of "go" is:', 'opts' => ['Goed','Gone','Went','Going'], 'correct' => 2],
    ],
    'Biology' => [
        ['q' => 'The powerhouse of the cell is the:', 'opts' => ['Nucleus','Mitochondria','Ribosome','Golgi body'], 'correct' => 1],
        ['q' => 'Photosynthesis mainly takes place in the:', 'opts' => ['Roots','Stem','Leaves','Flowers'], 'correct' => 2],
        ['q' => 'Which blood cells fight infection?', 'opts' => ['Red blood cells','White blood cells','Platelets','Plasma'], 'correct' => 1],
        ['q' => 'DNA is found mainly in the cell\'s:', 'opts' => ['Cytoplasm','Nucleus','Cell wall','Vacuole'], 'correct' => 1],
        ['q' => 'The process by which plants lose water vapor is called:', 'opts' => ['Respiration','Transpiration','Excretion','Photosynthesis'], 'correct' => 1],
        ['q' => 'Which organ pumps blood around the body?', 'opts' => ['Liver','Lungs','Heart','Kidney'], 'correct' => 2],
    ],
    'Chemistry' => [
        ['q' => 'The chemical symbol for Sodium is:', 'opts' => ['So','Sd','Na','S'], 'correct' => 2],
        ['q' => 'Water is made up of which two elements?', 'opts' => ['Hydrogen and Oxygen','Carbon and Oxygen','Hydrogen and Nitrogen','Oxygen and Nitrogen'], 'correct' => 0],
        ['q' => 'The pH of a neutral solution is:', 'opts' => ['0','7','14','1'], 'correct' => 1],
        ['q' => 'Which particle has no electric charge?', 'opts' => ['Proton','Electron','Neutron','Ion'], 'correct' => 2],
        ['q' => 'A substance that speeds up a reaction without being used up is a(n):', 'opts' => ['Reactant','Catalyst','Product','Solvent'], 'correct' => 1],
    ],
    'Physics' => [
        ['q' => 'The SI unit of force is the:', 'opts' => ['Joule','Newton','Watt','Pascal'], 'correct' => 1],
        ['q' => 'Which of these is a vector quantity?', 'opts' => ['Speed','Mass','Velocity','Time'], 'correct' => 2],
        ['q' => 'Sound cannot travel through:', 'opts' => ['Air','Water','Vacuum','Solid'], 'correct' => 2],
        ['q' => 'The unit of electrical resistance is the:', 'opts' => ['Volt','Ohm','Ampere','Watt'], 'correct' => 1],
        ['q' => 'Acceleration due to gravity on Earth is approximately:', 'opts' => ['5 m/s²','9.8 m/s²','15 m/s²','20 m/s²'], 'correct' => 1],
    ],
    'Economics' => [
        ['q' => 'The law of demand states that as price rises, quantity demanded:', 'opts' => ['Rises','Falls','Stays the same','Doubles'], 'correct' => 1],
        ['q' => 'GDP stands for:', 'opts' => ['Gross Domestic Product','General Domestic Price','Gross Development Plan','General Development Product'], 'correct' => 0],
        ['q' => 'Inflation refers to a general:', 'opts' => ['Rise in prices','Fall in prices','Rise in wages','Fall in unemployment'], 'correct' => 0],
        ['q' => 'Which is a factor of production?', 'opts' => ['Advertising','Labour','Profit','Interest'], 'correct' => 1],
    ],
    'Government' => [
        ['q' => 'A system with power divided between central and state governments is:', 'opts' => ['Unitary','Federal','Confederate','Monarchy'], 'correct' => 1],
        ['q' => 'The arm of government that makes laws is the:', 'opts' => ['Executive','Judiciary','Legislature','Civil service'], 'correct' => 2],
        ['q' => 'Democracy means government:', 'opts' => ['By the military','By one ruler','By the people','By the wealthy'], 'correct' => 2],
    ],
    'Computer Science' => [
        ['q' => 'HTML stands for:', 'opts' => ['HyperText Markup Language','HighText Markup Language','HyperText Making Language','HyperTool Markup Language'], 'correct' => 0],
        ['q' => 'What does CPU stand for?', 'opts' => ['Central Processing Unit','Computer Personal Unit','Central Program Utility','Control Processing Unit'], 'correct' => 0],
        ['q' => 'Which data structure uses FIFO (first in, first out)?', 'opts' => ['Stack','Queue','Tree','Graph'], 'correct' => 1],
        ['q' => 'Binary numbers use only the digits:', 'opts' => ['0 and 1','0 to 9','1 and 2','0 to 7'], 'correct' => 0],
        ['q' => 'SQL is mainly used to work with:', 'opts' => ['Images','Databases','Networks','Hardware'], 'correct' => 1],
    ],
    'Software Engineering' => [
        ['q' => 'Which SDLC phase involves gathering user needs before design begins?', 'opts' => ['Implementation','Requirements Analysis','Maintenance','Testing'], 'correct' => 1],
        ['q' => 'High cohesion within a module generally means:', 'opts' => ['It depends heavily on other modules','Its parts work together toward one purpose','It has no functions','It is slow'], 'correct' => 1],
        ['q' => 'Which testing type checks if the software meets user requirements?', 'opts' => ['Unit testing','Validation testing','Verification testing','Regression testing'], 'correct' => 1],
        ['q' => 'Big-O of O(n) describes an algorithm whose time grows:', 'opts' => ['Exponentially','Logarithmically','Linearly with input size','Not at all'], 'correct' => 2],
        ['q' => 'The Agile model is best described as:', 'opts' => ['Rigid and sequential','Iterative and incremental','One-phase only','Undocumented'], 'correct' => 1],
    ],
    'Systems Analysis And Design' => [
        ['q' => 'A DFD Level 0 diagram is also known as:', 'opts' => ['Context diagram','ER diagram','Flowchart','Gantt chart'], 'correct' => 0],
        ['q' => 'Which feasibility study checks if a system is affordable?', 'opts' => ['Technical','Economic','Operational','Schedule'], 'correct' => 1],
        ['q' => 'An ERD is primarily used to model:', 'opts' => ['Processes','Data relationships','User interfaces','Network topology'], 'correct' => 1],
        ['q' => 'CASE tools are used to:', 'opts' => ['Support system development','Only test hardware','Replace analysts entirely','Manage payroll'], 'correct' => 0],
    ],
];
