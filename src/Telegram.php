<?php

namespace irnix\Telegram;

use Exception;

class Telegram
{
	public static $mode = 'html';
	public static $errors_to;
	public static $errors_from;
	public static $logdir = '/tmp';
	public static $attempts = 3;

	protected static function log($message, $hash='', $level="Info") {
		$time = date("Y-m-d H:i:s");
		$hash = ($hash == '')? substr(md5($time.$message), 0, 8) : $hash;
		error_log($time.' '.$level.': '.$hash.': '.$message."\n",
		3,
		self::$logdir.'/telegram-'.getenv('USER').'.log');
		return $hash;
	}

	protected static function send_error($message) {
		if(self::$errors_to) {
			$subject = "=?UTF-8?B?".base64_encode('Telegram: сообщение не отправлено')."?=";
			$mail_from = self::$errors_from;
			$headers = ($mail_from)? "From: $mail_from <$mail_from>\r\nReply-To: $mail_from\r\n" : "";
			$headers .= "MIME-Version: 1.0\r\n" . 
						"Content-type: text/plain; charset=UTF-8\r\n";
			$options = ($mail_from)? "-f".$mail_from : "";
			mail(self::$errors_to, $subject, $message, $headers, $options);
		}
	}

	public static function send_chat($bot_id=null,$chat=null,$message='') {
		if (!$bot_id || !$chat) {
			return false;
		}
		$message = trim(strip_tags($message, '<b><strong><i><em><a><code><pre>'));
		if (!$message) {
			$mail = "Empty message";
			$id = self::log($mail,'','Warning');
			self::send_error($mail."\nID: ".$id."\n");
			return false;
		}
		$data = array(
			'disable_web_page_preview' => "true",
			'chat_id' => $chat,
			'parse_mode' => self::$mode,
			'text' => $message
		);
		$request = json_encode($data);
		$id = self::log($request);
		$url = 'https://api.telegram.org/bot'.$bot_id.'/sendMessage';
		$i = 1;
		while($i<=self::$attempts) {
			try {
				$mail = '';
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json',
					'Content-Length: '.strlen($request))
				);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_HEADER, false);
				$responce = curl_exec($ch);
				$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				if($responce === false) {
					throw new Exception(curl_errno($ch).': '.curl_error($ch));
				} elseif (($httpcode > 200 && $httpcode < 400) || $httpcode > 499) {
					throw new Exception('HTTP status: '.$httpcode);
				}else {
					$i = self::$attempts;
					$responce = json_decode($responce, true);
					if(!$responce['ok']) {
						throw new Exception($responce['error_code'].": ".$responce['description']);
					} else {
						self::log('OK', $id);
						return true;
					}
				}
			}
			catch (Exception $e) {
				self::log($e->getMessage(), $id."($i)", "Error");
				$mail = $e->getMessage()."\n\n";
				$mail.= "Сообщение:\n";
				$mail.= $data['text']."\n\n";
				$mail.= "ID: ".$id."\n";
				if ($i<self::$attempts) {
					sleep($i);
				}
				$i++;
			}
			finally {
				curl_close($ch);
			}
		}
		if ($mail != '') {
			self::send_error($mail);
        }
        return false;
	}
}
?>
