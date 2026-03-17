<?php

use App\Http\Controllers\Webhooks\StripeBillingWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('stripe', [StripeWebhookController::class, 'handle']);
Route::post('stripe-billing', [StripeBillingWebhookController::class, 'handle']);
