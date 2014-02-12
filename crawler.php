<?php

require 'vendor/autoload.php';
require 'xmlSerializer.php';

use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class RMITCrawler {

	public $client;
	public $crawler;
	private $crawlCourses = false;

	public function __construct() {
		$this->client = new Client();
	}

	public function dump($output = true) {
		unset($this->client);
		unset($this->patterns);
		unset($this->crawler);
		unset($this->crawlCourses);

		if ($output) {

			print_r($this);
		}

		return $this;
	}

	public function crawl($url) {
		$this->crawler = $this->client->request('GET', $url);
	}

// ---------------------------------------------------------------------

	public function crawlCourses($code) {
		$this->crawlCourses = true;
		$this->crawlProgramStructure($code);
	}
	public function crawlPrograms($code) {
		$this->crawlCourses = false;
		$this->crawlProgramStructure($code);
	}

	public function crawlProgramStructure($code) {

		$this->crawl('http://www.rmit.edu.au/programs/'.$code);

		$this->program = $code;
		try {
			$this->title = trim($this->crawler->filter('h1')->text());
		}
		catch (Exception $e) {  }

		if ($this->title == 'Central Authentication Service') {
			unset($this->title);
			$this->authRequired = 'Y';

			$form = $this->crawler->selectButton('Login')->form();

			try {
				$this->crawler = $this->client->submit($form, array('username' => 'e12883', 'password' => 'b0z0Feb4'));
				$this->authorised = TRUE;
			}
			catch (Exception $e) { }

		}

		if (!@$this->authRequired || @$this->authorised ) {

		//----------------------------------
			if (!$this->crawlCourses) {

				$this->available = 'Y';
				$this->replacedBy = '';

				try {
					$replacedBy = $this->crawler->filterXPath('//*[contains(text(),\'program is no longer offered to commencing students\')]');

					if ($replacedBy) {
						$replacedBy = $replacedBy->nextAll()->filter('a');
						$this->replacedBy = $this->extract($replacedBy->text(), 'code title')['code'];
						$this->available = 'N';
					}
				}
				catch (Exception $e) {  }

				try {
					$replacedBy = $this->crawler->filterXPath('//*[contains(text(),\'will be replaced with\')]');

					if ($replacedBy) {
						$replacedBy = $replacedBy->nextAll()->filter('a');
						$this->replacedBy = $this->extract($replacedBy->text(), 'code title')['code'];
						$this->available = 'N';
					}
				}
				catch (Exception $e) {  }

		//----------------------------------

				try {
					$notAvailable = $this->crawler->filterXPath('//*[contains(text(),\'program is no longer available to new students\')]')->text();
					if ($notAvailable) {
						$this->available = 'N';
					}
				}
				catch (Exception $e) {  }

				

		//----------------------------------
				$this->years = '';
				try {
					$duration = $this->crawler->filterXPath('//p[contains(text(),\'years full-time\')]')->text();

					// if (preg_match_all('/(one|two|three|four|five|six|seven|eight|nine|ten)/', $duration, $matched, PREG_PATTERN_ORDER)) {
					// $duration = $this->numeral($matched[0][0]);
					$duration = preg_replace('/^\D+/', '', $duration);
					$this->years = intval($duration);
				}
				catch (Exception $e) {  }
				try {
					$duration = $this->crawler->filterXPath('//p[contains(text(),\'year full-time\')]')->text();
					$this->years = 1;
				}
				catch (Exception $e) {  }
				if ($this->years==0) { $this->years = '?'; }
		//----------------------------------
				$this->industryConnections = '';
				try {
					$industryConnections = $this->crawler->filterXPath('//h2[contains(text(),\'Industry connections\')]')->nextAll()->filter('div')->first()->text();
					$this->industryConnections = trim($industryConnections);
				}
				catch (Exception $e) {  }
		//----------------------------------
				$this->professionalRecognition = '';
				try {
					$professionalRecognition = $this->crawler->filterXPath('//h3[contains(text(),\'Professional recognition and accreditation\')]')->nextAll()->filter('p, ul')->each( function($node) { 
						if ($node->getNode(0)->nodeName == 'ul') {
							return trim(implode(PHP_EOL, $node->filter('li')->each(function ($li) {
								return '- '.trim($li->text());
							})));
						}
						else {
							return trim($node->text());
						}
					});
					$this->professionalRecognition = trim(implode(PHP_EOL, $professionalRecognition));
				}
				catch (Exception $e) {  }
		//----------------------------------
				$this->globalOpportunities = '';
				try {
					$globalOpportunities = $this->crawler->filterXPath('//h2[contains(text(),\'Global opportunities\')]')->nextAll()->filter('div')->first()->text();
					$this->globalOpportunities = trim($globalOpportunities);	
				}
				catch (Exception $e) {  }
		//----------------------------------
				$this->owningSchool = '';
				try {
					$owningSchool = $this->crawler->filterXPath('//h2[contains(text(),\'Owning school\')]')->nextAll()->filter('div')->first()->text();
					$this->owningSchool = trim($owningSchool);
				}
				catch (Exception $e) {  }
			}
		//----------------------------------
			if ($this->crawlCourses) {
				try {
					$programStructures = $this->crawler->filterXPath('//a[contains(text(),\'following program structure(s)\')]')->link()->getUri();

					$this->crawlStructures($programStructures);
				}
				catch (Exception $e) {  }
			}

		}

		unset($this->authorised);

	}

// ---------------------------------------------------------------------

	public function crawlStructures($url) {

		$this->crawl($url);

		$hasMany = $this->crawler->filter('#groupName')->text() != 'Enrolment Program Structures';

		if ($hasMany) {
			$this->manyStructures = 'Y';
			
			$structures = $this->crawler->filter('td.path a')->each(function ($node) {
				return $node->link()->getUri();
			});

			foreach ($structures as $structureUri) {
				$this->crawl($structureUri);
				$this->crawlStructure($this->crawler);
			}

		}
		else {
			$this->crawlStructure($this->crawler);
		}

	}

	public function crawlStructure($crawler) {

		$structure = new stdClass();

		$details = array();

		$detailsItems = $crawler->filter('.contentArea')->first()->filter('p')->first()->html();

		$detailsItems = explode('<strong>', $detailsItems);

		foreach ($detailsItems as $detail) {
			$detail = $this->extract($detail, 'detail item');

			$detail['detail'] = $this->camel($detail['detail']);
			if ($detail['detail']) {
				$details[$detail['detail']] = trim(str_replace('<br>', PHP_EOL, $detail['item']));
			}
		}

		unset($details['rmitProgramCode']);
		unset($details['cricosCode']);

		$structure->details = $details;

		$allCourses = array();

		$courseTables = $crawler->filter('table.datatable')->each(function($table) use (&$allCourses) {

			$year = 0;
			try {
				$year = $this->numeral($this->extract($table->parents()->eq(1)->previousAll()->filterXPath('//h3[contains(text(),\'Year\')]')->first()->text(), 'year of program')['year']);
			}
			catch (Exception $e) { }
			
			$semester = '0';
			try {
				$semester = (string) $this->numeral($this->extract($table->parents()->eq(1)->previousAll()->filterXPath('//h4[contains(text(),\'Semester\')]')->first()->text(), 'semester course')['semester']);
			}
			catch (Exception $e) {}

			try {
				$elective = $table->parents()->eq(1)->previousAll()->first()->filterXPath('//h4[contains(text(),\'Select\')]')->text();
			}
			catch (Exception $e) {}
			try {
				$elective = $table->parents()->eq(1)->previousAll()->eq(1)->filterXPath('//td[contains(text(),\'OR\')]')->text();
			}
			catch (Exception $e) {}

			$elective = isset($elective) ? 'E' : 'C';

			$courses = $table->filter('tr')->each(function($tr) {
				return $tr->filter('td, th')->each(function ($td) {
					return $td->text();
				});

			});

			$headers = array_shift($courses);
			foreach ($headers as &$header) {
				$header = $this->camel($header);
			}
			foreach ( $courses as $key => &$row ) {
				if (count($row) < count($headers)) {
					$row = false;
				}
				else {
					$row = array_combine($headers, $row);
					$inSemester = array();
					if ($semester == '0') {
						if (!empty($row['semester1Class'])) {
							$inSemester[] = '1';
						}
						if (!empty($row['semester2Class'])) {
							$inSemester[] = '2';
						}
						$semester = implode(',', $inSemester);
					}
				}
			}

			if ($semester == '0') $semester = '';

			$courses = compact('year', 'semester', 'courses', 'elective');
			if (@$courses['courses'][0] == false) { unset($courses['courses']); }

			$allCourses[] = $courses;
		});


$structure->courses = $allCourses;

$this->structures[] = $structure;

}


// ---------------------------------------------------------------------

public static function camel($value) {
	$value = lcfirst(ucwords(trim(preg_replace('/\W+/', ' ', strtolower($value)))));

	return str_replace(' ', '', $value);
}

public function numeral($number) {
	$numerals = array(
		'one' => 1,
		'two' => 2,
		'three' => 3,
		'four' => 4,
		'five' => 5,
		'six' => 6,
		'seven' => 7,
		'eight' => 8,
		'nine' => 9,
		'ten' => 10,
		);

	return (int) @$numerals[strtolower($number)];
}

private $patterns = array(
	'code title' => '(?P<code>\S*) (?P<title>.*)',
	'detail item' => '(?P<detail>[^<]*)<\/strong>: (?P<item>.*)',
	'year of program' => 'Year (?P<year>\S*) of Program',
	'semester course' => 'Semester (?P<semester>\S*)(?:|\s*-\s*)\s*Complete',
	);

public function extract($text, $pattern) {

	$pattern = $this->_pattern($pattern);

	if (!$pattern) return $text;

	preg_match_all($pattern, trim($text), $result);

	$return = array();

	foreach ($result as $key => $value) {
		if (!is_numeric($key)) {
			$return[$key] = trim(@$value[0]);
		}
	}

	return $return;
}

private function _pattern($key) {

	if (isset($this->patterns[$key])) {
		return '/'.$this->patterns[$key].'/';
	}
	else {
		return false;
	}
}

}



if (isset($_POST['submit'])) {

	$xml = new XMLSerializer;
	$programs = array_filter(preg_split('/\W+/', $_POST['pasted']));
	$crawlCourses = $_POST['crawlCourses'];
	$downloadXml = isset($_POST['downloadXml']);

//$programs = array('BP070','BP284','BH078','BH082','MC225','BH086','BH068','MC224','MC190','BH074','BH084','BH070','BH089','BH093','BH090','BH076','BH092','BH100','MC230','MC229','MC228','MC227','MC226');
	$output = array();

	if (!$downloadXml) {
		header('Content-type: text/plain; charset=utf-8');
	}

	foreach ($programs as $program) {
		$r = new RMITCrawler;

		if ($crawlCourses == 'course') {
			$r->crawlCourses($program);
		}
		elseif ($crawlCourses == 'program') {
			$r->crawlPrograms($program);
		}

		if ($downloadXml) {
			$output[] = $r->dump(false);
		}
		else {
			$r->dump();
		}

	}

	if (!$downloadXml) { die; }

	$output = $xml->fromArray($output);

	if (count($programs) == 1) {
		$programs = array_shift($programs);
	}
	elseif (count($programs) > 3) {
		$programs = array_shift($programs) . '-' . array_pop($programs);
	}
	else {
		$programs = implode('_', $programs);
	}

	$filename = 'crawl' . ucfirst($crawlCourses) . '_' . $programs;

	// echo $filename;die;

	header('Content-type: application/xml; charset=utf-8');
	header("Content-Disposition: attachment; filename=$filename.xls;");

	echo $output; die;

}