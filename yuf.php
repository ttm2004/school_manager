<?php


// Bài 1: In phần tử

// Cho mảng:

$arr = [1, 2, 3, 4, 5,4, 3];

// 👉 In ra từng phần tử

// foreach($arr as $index){
//     echo $index . "\n";
// }

// Bài 2: Tính tổng số lượng phần tử trong mảng

// echo count($arr) . "\n";

// Bài 3: Tổng các phần tử trong mảng

// $sum = 0;
// foreach($arr as $index){
//     $sum += $index;
// }

// echo $sum . "\n";
// Bài 4: Tìm số lớn nhất / nhỏ nhất

// 👉 Không dùng max() / min()

// $max = $arr[0];

// foreach($arr as $index){
//     if($index > $max){
//         $max = $index;
//     }
// }
// echo $max . "\n";


// Bài 5: Tính trung bình cộng

// function avg($arr) {
//     $sum = array_sum($arr);
//     echo "Tổng: $sum\n";
//     $count = count($arr);
//     echo "Số lượng: $count\n";
//     return $count > 0 ? $sum / $count : 0;
// }

// echo avg($arr) . "\n";


// $count_even = 0;
// foreach($arr as $index){
//     ($index %2 == 0) ? $count_even++ : null;
// }
// echo "Số lượng số chẵn: $count_even\n";

// Bài 6: Tạo mảng mới chứa bình phương của các phần tử
// $squared = [];
// foreach($arr as $index){
//     $squared[] = $index * $index;
// }
// print_r($squared);

// Bài 7: Lọc ra các phần tử lớn hơn 3
// $filtered = [];
// foreach($arr as $index){ 
//     if($index > 3){
//         $filtered[] = $index;
//     }
// }
// print_r($filtered);

// $arr_reverse = [];

// for($i = count($arr); $i > 0; $i--){
//     $arr_reverse[] = $arr[$i-1];
// }
// print_r($arr_reverse);

// Bài 8: In ra phần từ trùng lặp


// $count = array_count_values($arr);

// foreach($count as $i => $num){
//     if($num > 1){
//         print_r("Số $i xuất hiện $num lần\n");
//     }
// }


// $arr = "Hello World";
// echo str_word_count($arr) . "\n";
// split(" ", $arr);
// print_r(explode(" ", $arr));    
array_unshift($arr, 0);
print_r($arr);  