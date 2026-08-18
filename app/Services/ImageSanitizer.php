<?php
namespace App\Services;
final class ImageSanitizer{
 /** Re-encodes common images when GD exists, stripping EXIF/ancillary metadata. Returns true if re-encoded. */
 public function sanitize(string $path,string $mime):bool{
  if(!extension_loaded('gd'))return false;
  $img=match($mime){'image/jpeg'=>@imagecreatefromjpeg($path),'image/png'=>@imagecreatefrompng($path),'image/webp'=>function_exists('imagecreatefromwebp')?@imagecreatefromwebp($path):false,default=>false};
  if(!$img)return false;
  $tmp=$path.'.sanitized';
  $ok=match($mime){'image/jpeg'=>imagejpeg($img,$tmp,90),'image/png'=>imagepng($img,$tmp,6),'image/webp'=>function_exists('imagewebp')?imagewebp($img,$tmp,90):false,default=>false};
  imagedestroy($img);
  if($ok){rename($tmp,$path);return true;}@unlink($tmp);return false;
 }
}