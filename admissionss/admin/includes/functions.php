<?php

function timeAgo($datetime) {

    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) {
        return "Vừa xong";
    }

    $minutes = floor($diff / 60);
    if ($minutes < 60) {
        return $minutes . " phút trước";
    }

    $hours = floor($diff / 3600);
    if ($hours < 24) {
        return $hours . " giờ trước";
    }

    $days = floor($diff / 86400);
    if ($days < 7) {
        return $days . " ngày trước";
    }

    return date("d/m/Y H:i", $time);
}
?>