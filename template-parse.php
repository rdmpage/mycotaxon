<?php


require_once (dirname(__FILE__) . '/HtmlDomParser.php');
use Sunra\PhpSimple\HtmlDomParser;

$basedir = dirname(__FILE__) . '/html-mycotaxon';

$field_to_ris_key = array(
	'title' 	=> 'TI',
	'journal' 	=> 'JO',
	'issn' 		=> 'SN',
	'volume' 	=> 'VL',
	'issue' 	=> 'IS',
	'spage' 	=> 'SP',
	'epage' 	=> 'EP',
	'year' 		=> 'Y1',
	'date'		=> 'PY',
	'abstract'	=> 'N2',
	'url'		=> 'UR',
	'pdf'		=> 'L1',
	'doi'		=> 'DO',

	'authors'	=> 'AU',

	'publisher'	=> 'PB',
	'publoc'	=> 'PP',
	);

$keys = array(
'title',
'authors',
'journal',
'issn',
'volume',
'issue',
'spage',
'epage',
'year',
'doi',
'url'
);


$files = scandir($basedir);

// debugging
/*
$files=array('0073.html');
$files=array('0100.html');
$files=array('0120.html');
$files=array('0001.html');
*/

foreach ($files as $filename)
{
	// echo "filename=$filename\n";
	
	if (preg_match('/\.html$/', $filename))
	{	
		$html = file_get_contents($basedir . '/' . $filename);
		
		// fix fuckups
		$lines = explode("\n", $html);
		
		//print_r($lines);
		
		$p_count = 0;
		foreach ($lines as &$line)
		{
			if (preg_match('/^\s*\<P/', $line))
			{
				$p_count++;
				if ($p_count > 1)
				{
					$p_count++;
					$line = preg_replace('/^\s*\<P/', '</P><P', $line);
				}
			}
		}
		
		// print_r($lines);
		// echo $p_count;
		//exit();
		
		$html = join("\n", $lines);
		
		$dom = HtmlDomParser::str_get_html($html);

		$journal = '';
		$issn = '';
		$volume = '';
		$issue = '';
		$date = '';
		$year = '';
		
		$base_url = '';
				
		foreach ($dom->find('body font p') as $p)
		{
			// echo "---\n" . $p->plaintext . "\n---\n";
			
			// Journal issue metadata
			
			// Mycotaxon 73, 1999 (October-December).
			if (preg_match('/(?<journal>Mycotaxon)\s+(?<volume>\d+),\s+(?<year>[0-9]{4})/', $p->plaintext, $m))
			{
				// print_r($m);
				
				$journal 	= $m['journal'];
				$issn 		= '0093-4666';
				$volume		= $m['volume'];	
				$year 		= $m['year'];
				
				$base_url = 'http://www.cybertruffle.org.uk/cyberliber/59575/' . str_pad($volume, 4, '0', STR_PAD_LEFT);
			}
			
			
			// Mycotaxon 74 (1), 2000 (January-March) 
			if (preg_match('/(?<journal>Mycotaxon)\s+(?<volume>\d+)\s+\((?<issue>[^\)]+)\),\s+(?<year>[0-9]{4})/', $p->plaintext, $m))
			{
				// print_r($m);
				
				$journal 	= $m['journal'];
				$issn 		= '0093-4666';
				$volume		= $m['volume'];	
				$issue		= $m['issue'];							
				$year 		= $m['year'];
				
				$base_url = 'http://www.cybertruffle.org.uk/cyberliber/59575/' . str_pad($volume, 4, '0', STR_PAD_LEFT);
			}
			
			foreach ($p->find('table tbody tr') as $tr)
			{
				// echo $tr->plaintext . "\n";
				
				$reference = new stdclass;
				
				$reference->genre = 'article';
				
				$reference->journal = $journal;
				$reference->issn = $issn;
				$reference->volume = $volume;
				
				if ($issue != '')
				{
					$reference->issue = $issue;
				}				
				
				if ($date == '')
				{
					$reference->year = $year;
				}
				else
				{
					$reference->date = $date;
				}
				
				if (preg_match('/(?<title><b>(Author [I|i]ndex|Content|Errata|Erratum|Index|Instructions to authors|Journal Publication Statement|Nomenclatural novelties|Notice|Ownership Statement|Publication [D|d]ate|Publication [D|d]ates? [F|f]or Mycotaxon|Reviewers)\.?<\/b>.*)\s+Pages?/u', $tr->outertext, $m))
				{
					$reference->title = $m['title'];					
				}
				else
				{				
					if (preg_match('/<b>(?<authorstring>.*)<\/b>\s*(?<title>.*)\s+Pages?/Uu', $tr->outertext, $m))
					{
						// print_r($m);
					
						$authorstring =  $m['authorstring'];
						$authorstring = html_entity_decode($authorstring, ENT_QUOTES | ENT_HTML5, 'UTF-8');
						$authorstring = preg_replace('/\.([A-Z])/', '. $1', $authorstring);
					
						$reference->authors = preg_split("/;\s*/", $authorstring);
					
						$reference->title = $m['title'];
					}
				}
				
				if (isset($reference->title))
				{
					$reference->title = strip_tags($reference->title);
					$reference->title = html_entity_decode($reference->title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
					$reference->title = preg_replace('/\.$/u', '', $reference->title);
					$reference->title = preg_replace('/^\.\s*/u', '', $reference->title);
					$reference->title = preg_replace('/^\s+/u', '', $reference->title);
					$reference->title = preg_replace('/\s\s+/u', ' ', $reference->title);				
				}
								
				$pages = array();
				foreach ($tr->find('a') as $a)
				{
					$pages[] = $a->plaintext;
					//echo $a->href . ' ' . "\n";
					
					if (!isset($reference->url))
					{
						$reference->url = $base_url . '/' . $a->href;
					}
				}
				
				// print_r($pages);
				
				$n = count($pages);
				
				$reference->spage = $pages[0];
				if ($n > 1)
				{
					$reference->epage = $pages[$n - 1];
				}
				
				//print_r($reference);
				
				$go = true;
				
				// do we have an article (not administrivia)
				if (!preg_match('/\/\d+\.html?$/', $reference->url))
				{
					$go = false;
				}
				
				if (!isset($reference->title))
				{
					$go = false;
				}
				
					
				//$go = false;
				
				if ($go)
				{
					echo 'TY  - JOUR' . "\n";
		
					foreach ($reference as $k => $v)
					{
						switch ($k)
						{
							case 'title':
							case 'journal':
							case 'volume':
							case 'issue':
							case 'spage':
							case 'epage':
							case 'year':
							case 'issn':
							case 'url':
							case 'doi':
								if (isset($field_to_ris_key[$k]))
								{
									echo $field_to_ris_key[$k] . '  - ' . $v . "\n";
								}
								break;
					
							case 'authors':
								foreach ($v as $a)
								{
									echo $field_to_ris_key[$k] . '  - ' . mb_convert_case($a, MB_CASE_TITLE) . "\n";
								}
								break;
			
							default:
								break;
						}
					}
		
					echo "ER  - \n\n";					
					
				}
								
				
				
			}

		}
		
	}

}

		
?>

