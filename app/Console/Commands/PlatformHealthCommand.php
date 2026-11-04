<?php
namespace App\Console\Commands;
use App\Services\SystemHealth;use Illuminate\Console\Command;
class PlatformHealthCommand extends Command{
 protected $signature='platform:health {--json}';protected $description='Run Private Gather deployment health checks.';
 public function handle(SystemHealth $health):int{$r=$health->report();if($this->option('json')){$this->line(json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));return $r['healthy']?self::SUCCESS:self::FAILURE;}$this->info('Platform '.$r['version']);foreach($r['checks'] as $name=>$c)$this->line(($c['ok']?'PASS':'FAIL').' '.$name.' — '.$c['value']);return $r['healthy']?self::SUCCESS:self::FAILURE;}
}