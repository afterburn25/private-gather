<?php
namespace Tests\Unit;
use App\Services\PlaceholderRenderer;use PHPUnit\Framework\TestCase;
class PlaceholderRendererTest extends TestCase{
 public function test_known_tokens_render_and_unknown_tokens_remain():void{$r=new PlaceholderRenderer();$out=$r->render('Hello {{ member.display_name }} at {{ event.name }} / {{ unsafe.foo }}',['member'=>['display_name'=>'Alex'],'event'=>['name'=>'Night']]);$this->assertSame('Hello Alex at Night / {{ unsafe.foo }}',$out);}
}