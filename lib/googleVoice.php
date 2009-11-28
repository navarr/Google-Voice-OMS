<?php
class GoogleVoice
{
	public $username;
	public $password;

	protected $lastURL;
	protected $crumb;

	public function __construct($username, $password)
	{
		$this->username = $username;
		$this->password = $password;
		$this->login();
	}
	// Login to Google Voice
	private function login()
	{
		$html = $this->curl('http://www.google.com/voice/m');

		$action = $this->match('!<form.*?action="(.*?)"!ms', $html, 1);

		preg_match_all('!<input.*?type="hidden".*?name="(.*?)".*?value="(.*?)"!ms', $html, $hidden);

		$post = "Email={$this->username}&Passwd={$this->password}";
		for ($i = 0; $i < count($hidden[0]); $i++)
		$post .= '&'.$hidden[1][$i].'='.urlencode($hidden[2][$i]);

		$html = $this->curl($action, $this->lastURL, $post);

		$this->crumb = urlencode($this->match('!<input.*?name="_rnr_se".*?value="(.*?)"!ms', $html, 1));
		
		if(!$this->crumb)
			{ throw new Exception("Unable to Log In to Google Voice"); }
	}
	// Connect $you to $them. Takes two 10 digit US phone numbers.
	public function call($you, $them)
	{
		$you = preg_replace('/[^0-9]/', '', $you);
		$them = preg_replace('/[^0-9]/', '', $them);

		$crumb = $this->crumb;

		$post = "_rnr_se=$crumb&phone=$you&number=$them&call=Call";
		$html = $this->curl("https://www.google.com/voice/m/sendcall", $this->lastURL, $post);
	}
	public function sms($them, $smtxt)
	{
		$them = preg_replace('/[^0-9]/', '', $them);

		$crumb = $this->crumb;

		$post = "_rnr_se=$crumb&number=$them&smstext=$smtxt&submit=Send";
		$html = $this->curl("https://www.google.com/voice/m/sendsms", $this->lastURL, $post);
		return $html;
	}
	public function get_number()
	{
		$raw = $this->curl("https://www.google.com/voice/m");
		preg_match("#\<b class=\"ms3\"\>([^<]+)\</b\>#i",$raw,$matches);
		$number = str_replace
		(
			array(" ","(",")","-"),
		"",$matches[1]);
		return "1".$number;
	}
	protected function curl($url, $referer = null, $post = null, $return_header = false)
	{
		static $tmpfile;

		if (! isset ($tmpfile) || ($tmpfile == ''))
			{ $tmpfile = tempnam('/tmp', 'FOO'); }

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $tmpfile);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $tmpfile);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0); 
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (iPhone; U; CPU iPhone OS 2_2_1 like Mac OS X; en-us) AppleWebKit/525.18.1 (KHTML, like Gecko) Version/3.1.1 Mobile/5H11 Safari/525.20");

		if ($referer)
			{ curl_setopt($ch, CURLOPT_REFERER, $referer); }

		if (!is_null($post))
		{
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}

		if ($return_header)
		{
			curl_setopt($ch, CURLOPT_HEADER, 1);
			$html = curl_exec($ch);
			$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$this->lastURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
			return substr($html, 0, $header_size);
		}
		else
		{
			$html = curl_exec($ch);
			$this->lastURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
			if($html === false) { throw new Exception($url." - ".curl_error($ch)); }
			return $html;
		}
	}
	protected function match($regex, $str, $i = 0)
	{
		return preg_match($regex, $str, $match) == 1?$match[$i]:false;
	}
}
