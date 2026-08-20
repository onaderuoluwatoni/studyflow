<?php
if (!function_exists('sfIsMember')) {
    function sfIsMember($conn, $groupId, $user_id) {
        $stmt = $conn->prepare("SELECT 1 FROM community_group_members WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $groupId, $user_id);
        $stmt->execute();
        $has = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $has;
    }
}
