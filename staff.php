<?php

require 'vendor/autoload.php';

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class RMITCrawler {

	public $client;
	public $crawler;

	public function __construct() {
		$this->client = new Client();
	}

	public function dump($output = true) {
		unset($this->client);
		unset($this->patterns);
		unset($this->crawler);

		if ($output) {

			print_r($this);
		}

		return $this;
	}

	public function crawl($url) {
		$this->crawler = $this->client->request('GET', $url);
	}

// ---------------------------------------------------------------------

	public function crawlStaff($staff) {

		$this->crawl('http://www.rmit.edu.au/browse;FORMQRY=SEARCHTYPE=PEOPLE&ADV=Search&name='.$staff.'&acc=0&abb=T&staffno=&phone=&st=T&submit3=Search?QRY=Type=%22StaffProfile%22%20(%20StaffName=(%7C%22'.$staff.'%22%20))');

		try {
			$name = trim($this->crawler->filter('h1')->text());
		}
		catch (Exception $e) {  }

		try {
			$details = array();
			
			$this->crawler->filter('.datatable tr')->each(function ($tr) use (&$details) {
				$th = trim($tr->filter('th')->text());
				$td = $tr->filter('td')->first();

				if ($th == 'Contact Details') {

					$td->filter('p')->each(function ($p) use (&$details) {
						if (stristr($p->text(), '@') !== FALSE) {
							$th = 'Email';
							$td = trim($p->text());
						}
						elseif (stristr($p->text(),'3') !== FALSE) {
							$th = 'Phone';
							$td = str_replace('+61 3', '03', $p->text());
						}
						else {
							$th = '';
							$td = '';
						}

						$details[] = array($th, $td);
					});

				}
				elseif ($th == 'Location') {

					$td->filter('p')->each(function ($p) use (&$details) {

						if (stristr($p->text(), 'Campus') !== FALSE) {
							$th = 'Campus';
							$td = trim(str_replace('Campus','',$p->text()));
							$details[] = array($th, $td);
						}
						else {
							
							$td = explode('<br>', str_replace('&#13;','',$p->html()));
							foreach ($td as $tdrow) {
								list($th, $td) = explode(':',$tdrow);
								$details[] = array(trim($th), trim($td));
							}
						}
					});
				}
				else {

					if (stristr($th, 'School') !== FALSE) {
						$th = 'School';
					}
					if (stristr($th, 'College') !== FALSE) {
						$th = 'College';
					}

					$td = trim($td->text());
					$details[] = array($th, $td);
				}

			});
}
catch (Exception $e) {  }

if (isset($name)) {
	echo '<table id="nameDetails">'.PHP_EOL.'<tr><th>Name</th><td>'.$name.'</td></tr>'.PHP_EOL;
	foreach (@$details as $detail) {
		echo '<tr><th>'.$detail[0].'</th><td>'.$detail[1].'</td></tr>'.PHP_EOL;
	}
	echo '</table>';
}

}

}

if (isset($_GET['staff'])) {

	$r = new RMITCrawler;
	$r->crawlStaff($_GET['staff']);

}