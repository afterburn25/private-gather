<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration{
 public function up():void{Schema::table('media_assets',function(Blueprint $t){$t->string('visibility')->default('tenant')->index();$t->string('sha256',64)->nullable()->index();$t->boolean('metadata_stripped')->default(false);});}
 public function down():void{Schema::table('media_assets',fn(Blueprint $t)=>$t->dropColumn(['visibility','sha256','metadata_stripped']));}
};