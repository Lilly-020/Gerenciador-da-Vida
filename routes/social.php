<?php

use App\Http\Controllers\SocialController;
use App\Http\Controllers\SocialFriendshipController;
use App\Http\Controllers\SocialPostController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('social')->name('social.')->group(function () {
    Route::get('/', [SocialController::class, 'index'])->name('index');
    Route::get('/friends', [SocialController::class, 'index'])->name('friends');
    Route::get('/requests', [SocialController::class, 'index'])->name('requests');
    Route::get('/profile/{person}', [SocialController::class, 'page'])->name('profile');
    Route::get('/profile/{person}/data', [SocialController::class, 'profile'])->name('profile.data');
    Route::get('/profile/{person}/image/{image}', [SocialController::class, 'profileImage'])->whereIn('image', ['avatar', 'cover'])->name('profile.image');
    Route::get('/feed', [SocialController::class, 'feed'])->name('feed');
    Route::get('/people', [SocialController::class, 'people'])->middleware('throttle:90,1,social-people')->name('people');
    Route::get('/notifications', [SocialController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/{notification}', [SocialController::class, 'openNotification'])->whereUuid('notification')->name('notifications.open');

    Route::post('/posts', [SocialPostController::class, 'store'])->middleware('throttle:10,1,social-posts')->name('posts.store');
    Route::patch('/posts/{post}', [SocialPostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [SocialPostController::class, 'destroy'])->name('posts.destroy');
    Route::get('/media/{media}', [SocialPostController::class, 'media'])->name('media');
    Route::post('/posts/{post}/like', [SocialPostController::class, 'like'])->middleware('throttle:60,1,social-likes')->name('posts.like');
    Route::get('/posts/{post}/comments', [SocialPostController::class, 'comments'])->name('comments.index');
    Route::post('/posts/{post}/comments', [SocialPostController::class, 'storeComment'])->middleware('throttle:30,1,social-comments')->name('comments.store');
    Route::put('/comments/{comment}/reaction', [SocialPostController::class, 'reactToComment'])->middleware('throttle:60,1,social-comment-reactions')->name('comments.reaction');
    Route::patch('/comments/{comment}', [SocialPostController::class, 'updateComment'])->name('comments.update');
    Route::delete('/comments/{comment}', [SocialPostController::class, 'destroyComment'])->name('comments.destroy');

    Route::post('/friendships', [SocialFriendshipController::class, 'store'])->middleware('throttle:15,1,social-friendships')->name('friendships.store');
    Route::patch('/friendships/{friendship}', [SocialFriendshipController::class, 'update'])->name('friendships.update');
    Route::delete('/friendships/{friendship}', [SocialFriendshipController::class, 'destroy'])->name('friendships.destroy');
    Route::post('/blocks/{person}', [SocialFriendshipController::class, 'block'])->middleware('throttle:30,1,social-blocks')->name('blocks.store');
    Route::delete('/blocks/{person}', [SocialFriendshipController::class, 'unblock'])->name('blocks.destroy');
});
