<?php
namespace App\Services;
final class TotpService{
 private const ALPHABET='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
 public function secret(int $bytes=20):string{return $this->base32Encode(random_bytes($bytes));}
 public function uri(string $secret,string $account,string $issuer):string{return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?secret='.rawurlencode($secret).'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';}
 public function verify(string $secret,string $code,int $window=1):bool{
  $code=preg_replace('/\D/','',$code)??'';if(strlen($code)!==6)return false;$step=intdiv(time(),30);
  for($i=-$window;$i<=$window;$i++)if(hash_equals($this->code($secret,$step+$i),$code))return true;return false;
 }
 public function recoveryCodes(int $count=8):array{$out=[];for($i=0;$i<$count;$i++)$out[]=strtolower(bin2hex(random_bytes(5)));return $out;}
 private function code(string $secret,int $counter):string{$key=$this->base32Decode($secret);$bin=pack('N2',intdiv($counter,0x100000000),$counter%0x100000000);$hash=hash_hmac('sha1',$bin,$key,true);$offset=ord($hash[19])&0xf;$n=((ord($hash[$offset])&0x7f)<<24)|(ord($hash[$offset+1])<<16)|(ord($hash[$offset+2])<<8)|ord($hash[$offset+3]);return str_pad((string)($n%1000000),6,'0',STR_PAD_LEFT);}
 private function base32Encode(string $data):string{$bits='';foreach(str_split($data) as $c)$bits.=str_pad(decbin(ord($c)),8,'0',STR_PAD_LEFT);$out='';foreach(str_split($bits,5) as $chunk){$chunk=str_pad($chunk,5,'0');$out.=self::ALPHABET[bindec($chunk)];}return $out;}
 private function base32Decode(string $data):string{$data=strtoupper(preg_replace('/[^A-Z2-7]/','',$data)??'');$bits='';foreach(str_split($data) as $c){$p=strpos(self::ALPHABET,$c);if($p===false)continue;$bits.=str_pad(decbin($p),5,'0',STR_PAD_LEFT);}$out='';foreach(str_split($bits,8) as $b)if(strlen($b)===8)$out.=chr(bindec($b));return $out;}
}