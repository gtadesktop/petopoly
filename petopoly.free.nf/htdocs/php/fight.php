<?php
header('Content-Type: application/json');
require 'db.php';
session_start();
$uid = $_SESSION['uid'] ?? 0;

$petA = json_decode($_POST['petA'], true);
$petB = json_decode($_POST['petB'], true);
// Einfacher RNG-Fight: Stärke + Zufall
$scoreA = $petA['attack'] * rand(1,10);
$scoreB = $petB['defense'] * rand(1,10);
$winner = ($scoreA >= $scoreB) ? 'A' : 'B';
// DB-Update: wins/losses, evtl. Level-Up
echo json_encode(['winner'=>$winner]);