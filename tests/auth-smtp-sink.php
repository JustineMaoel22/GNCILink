<?php
// A real local SMTP protocol receiver for synthetic integration tests only.
if(PHP_SAPI!=='cli'||!preg_match('/^gnci-auth-mail-[a-f0-9]{16}$/D',basename($argv[1]??'')))exit(1);
$file=$argv[1];$server=stream_socket_server('tcp://127.0.0.1:2527',$code,$error);if(!$server)exit(2);
while($client=@stream_socket_accept($server,25)){
 fwrite($client,"220 localhost test SMTP\r\n");$data=false;$message='';$auth=0;
 while(($line=fgets($client))!==false){
  if($data){if(rtrim($line)==='.') {file_put_contents($file,json_encode($message)."\n",FILE_APPEND|LOCK_EX);fwrite($client,"250 accepted\r\n");$data=false;}else $message.=$line;continue;}
  if($auth){$auth--;fwrite($client,$auth?"334 UGFzc3dvcmQ6\r\n":"235 authenticated\r\n");continue;}
  if(str_starts_with($line,'EHLO'))fwrite($client,"250-localhost\r\n250 AUTH LOGIN\r\n");
  elseif(str_starts_with($line,'AUTH LOGIN')){$auth=2;fwrite($client,"334 VXNlcm5hbWU6\r\n");}
  elseif(str_starts_with($line,'DATA')){$data=true;fwrite($client,"354 end with dot\r\n");}
  elseif(str_starts_with($line,'QUIT')){fwrite($client,"221 bye\r\n");break;}
  else fwrite($client,"250 OK\r\n");
 }
 fclose($client);
}
fclose($server);
