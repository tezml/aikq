<?php
/**
 * Created by PhpStorm.
 * User: 11247
 * Date: 2018/7/18
 * Time: 15:22
 */

namespace App\Console\Anchor;


use App\Models\Anchor\AnchorRoom;
use Illuminate\Console\Command;

class StreamKeyFrameCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'anchor_key_frame:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '检查主播房间直播流是否切断';

    /**
     * Create a new command instance.
     * HotMatchCommand constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //获取正在直播的主播房间
        $query = AnchorRoom::query()->where('status', AnchorRoom::kStatusLiving);
        //$query->where('updated_at', '<=', date('Y-m-d H:i', strtotime('-5 minutes')));
        $rooms = $query->get();
        foreach ($rooms as $room) {
            $stream = $room->live_flv;
            if (empty($stream)) {
                $stream = $room->live_m3u8;
            }
            if (empty($stream)) {
                $stream = $room->live_rtmp;
            }

            if (!empty($stream)) {
                $outPath = storage_path('app/public/cover/room/' . $room->id . '.jpg');
                self::spiderRtmpKeyFrame($stream, $outPath);
                $room->live_cover = "/cover/room/" . $room->id . ".jpg";
                $room->save();
            }
        }
    }

    public function spiderKeyFrame($stream, $outPath) {
        exec('ffmpeg -i "' . $stream . '" -y -vframes 1 -f image2 ' . $outPath);
    }

    public static function spiderRtmpKeyFrame($stream, $outPath) {
        exec("ffmpeg -i $stream -f image2 -ss 1 -y -vframes 1 -s 220*135 $outPath");
    }

}