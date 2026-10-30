<?php

use App\Http\Controllers\Game\GameRoomController;
use App\Http\Controllers\Game\GameScoreController;
use Illuminate\Support\Facades\Route;

Route::get('config', [GameRoomController::class, 'config'])->name('config');
Route::get('rooms', [GameRoomController::class, 'index'])->name('rooms.index');
Route::post('rooms', [GameRoomController::class, 'store'])->name('rooms.store');
Route::post('rooms/join', [GameRoomController::class, 'join'])->name('rooms.join');
Route::post('rooms/match', [GameRoomController::class, 'match'])->name('rooms.match');
Route::get('rooms/{code}', [GameRoomController::class, 'show'])->name('rooms.show');
Route::post('rooms/{code}/watch', [GameRoomController::class, 'watch'])->name('rooms.watch');
Route::post('rooms/{code}/actions', [GameRoomController::class, 'act'])->name('rooms.act');
Route::post('rooms/{code}/input', [GameRoomController::class, 'input'])->middleware('throttle:game-input')->name('rooms.input');
Route::post('rooms/{code}/leave', [GameRoomController::class, 'leave'])->name('rooms.leave');
Route::post('rooms/{code}/gamemaster', [GameRoomController::class, 'gamemaster'])->middleware('throttle:20,1')->name('rooms.gamemaster');
Route::get('scores', [GameScoreController::class, 'index'])->name('scores.index');
Route::post('scores', [GameScoreController::class, 'store'])->middleware('throttle:30,1')->name('scores.store');
