<?php
// php/calendar.php
header('Content-Type: application/json');
$now = time();
$secPerMin = 10;
$minPerHr  = 60;
$hrPerDay  = 24;
$dayPerWeek= 7;
$weekPerMon= 4;
$monPerYear=12;

$totalMin = intdiv($now, $secPerMin);
$minute   = $totalMin % $minPerHr;
$totalH   = intdiv($totalMin, $minPerHr);
$hour     = $totalH % $hrPerDay;
$totalD   = intdiv($totalH, $hrPerDay);
$day      = ($totalD % $dayPerWeek) +1;
$totalW   = intdiv($totalD, $dayPerWeek);
$week     = ($totalW % $weekPerMon)+1;
$totalM   = intdiv($totalW, $weekPerMon);
$month    = ($totalM % $monPerYear)+1;
$year     = intdiv($totalM, $monPerYear);

echo json_encode(compact('year','month','week','day','hour','minute'));