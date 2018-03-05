<?php

namespace App\Console;

use App\Http\Controllers\Mobile\Live\LiveController;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Request;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        IndexCommand::class,//直播 列表静态化
        LiveDetailCommand::class,//PC终端、移动终端html缓存。

        PlayerJsonCommand::class,//静态化赛前1小时和正在比赛的 线路
        NoStartPlayerJsonCommand::class,//静态化 赛前1小时前未开始的 线路

        DeleteExpireFileCommand::class,//删除过期文件

        LivesJsonCommand::class,//列表json静态化
        DBSpreadCommand::class,
        TTzbPlayerJsonCommand::class,//天天直播 （开始前1小时 - 开始后 3小时的比赛） 线路静态化
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('live_json_cache:run')->everyMinute();//每分钟刷新一次赛事缓存
        $schedule->command('index_cache:run')->everyMinute();//每分钟刷新主页缓存
        $schedule->command('live_detail_cache:run')->everyFiveMinutes();//每5分钟刷新终端缓存

        $schedule->command('player_json_cache:run')->everyFiveMinutes();//->everyMinute();//5分钟刷新一次正在直播的比赛的线路内容
        $schedule->command('ns_player_json_cache:run')->everyFiveMinutes();//->everyMinute();//5分钟刷新一次未开始比赛的线路内容 一小时内执静态化所有的json

        $schedule->command('delete_cache:run')->dailyAt('07:00');//每天删除一次文件

        //$schedule->command('mobile_detail_cache:run')->everyMinute();//每五分钟刷新移动直播终端缓存

        $mController = new LiveController();
        $schedule->call(function() use($mController){
            $mController->matchLiveStatic(new Request());//每分钟刷新比赛状态数据
        })->everyMinute();

        $schedule->command('db_spread_cache:run')->hourlyAt(45);

        $schedule->command('ttzb_player_json_cache:run')->cron('*/2 * * * *');//2分钟刷新一次天天直播的线路。
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
