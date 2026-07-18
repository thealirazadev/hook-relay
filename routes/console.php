<?php

use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', ['--model' => [WebhookEvent::class]])->daily();
