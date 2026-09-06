<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:cancel-unpaid-bookings')->hourly();

Schedule::command('app:complete-past-events')->hourly();
Schedule::command('app:send-event-reminders')->dailyAt('08:00');