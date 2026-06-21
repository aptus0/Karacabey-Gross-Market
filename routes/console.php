<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\SyncErp12ProductsJob;
use App\Jobs\SyncDataConnectionProductsJob;
use App\Jobs\TrackActiveShipmentsJob;

Schedule::job(new TrackActiveShipmentsJob)->everyFifteenMinutes();
Schedule::job(new SyncDataConnectionProductsJob)->everyFiveMinutes()->withoutOverlapping();
Schedule::job(new SyncErp12ProductsJob)->everyFiveMinutes()->withoutOverlapping();
Schedule::command('kgm:enforce-commerce-rules')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('erp12:sync-admin-snapshot')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('kgm:ai-product-seo --limit=100 --chunk=20')->hourly()->withoutOverlapping(180);
Schedule::command('kgm:seo-xml')->dailyAt('03:20')->withoutOverlapping();
