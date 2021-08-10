<?php

function pullFromPW() {

// TODO: replace this janky function with something in the more-suitable style of the other two
// ie, the other functions handle RSS better -- and: the PW RSS feed INCLUDES a <content> tag.
// which means this doesn't need to constantly make HTTP requests to each article
// will update a later date

	$frontpage = simplexml_load_file("https://www.peoplesworld.org/feed/?post_type=article");


	$json = json_encode($frontpage);

	$pw = json_decode($json, TRUE); // a strange roundabout process, but necessary
	/* why necessary?
	PHP's simplexml_load_file() function produces something which, generally speaking, ACTS like an array, but ISN'T an array.
	Which means, I can't refer to $frontpage[0]["stuff"]["morestuff"] by default. PHP complains that I'm treating an "XML Element" as though it were an array.
	Json_encode() converts it to json, and json_decode() converts it to ARRAYS. So, that's why this silly process is necessary here.
	*/
	
	
	/* Prepare iterator */
	$i = 0;
	
	
	/* Write LaTeX headings. Page 5.5x8.5, ie, landscape Letter Paper, half-page
	Chapter headings not printed, used anyway. (Writing \chapter{} puts the following content on its own page, so that each article starts at the top of a page. Looks neater. */
	$newspaper = '\documentclass[oneside,12pt,openany]{book}
\usepackage[paperwidth=5.5in,paperheight=8.5in,total={4.8in,8.0in}]{geometry}
\pdfpageheight=8.5in
\pdfpagewidth=5.5in
\usepackage[utf8]{inputenc}
\usepackage{graphicx}
\usepackage{grffile}
\makeatletter
\def\@makechapterhead#1{%
  \vspace*{0\p@}%
  {\parindent \z@ \raggedright \normalfont
    \interlinepenalty\@M
    \Large \bfseries #1\par\nobreak
    \vskip 0\p@
  }}
\def\@makeschapterhead#1{%
  \vspace*{0\p@}%
  {\parindent \z@ \raggedright
    \normalfont
    \interlinepenalty\@M
    \Large \bfseries  #1\par\nobreak
    \vskip 0\p@
  }}
\makeatother
\begin{document}
	\begin{center}
		\begin{Large}
'.date("d F, Y").'
		\end{Large}
		
		\vfill
	
		\includegraphics[width=10cm,height=10cm,keepaspectratio]{./pw-logo.png}
	
		\vfill
		
	\end{center}'.PHP_EOL.PHP_EOL;
	
	
	/* Cycle through articles from RSS feed */
	while ($i < count($pw["channel"]["item"])) {
	
		/* Load particular article into memory */
		$article = file_get_contents($pw["channel"]["item"][$i]["link"]);

		/* Grab author name */
		preg_match('/<a class="author".*<\/a>/', $article, $author);
		$author = preg_replace('/<a.*">/', "", $author[0]);
		$author = str_replace('</a>', PHP_EOL.PHP_EOL, $author);


		/* Grab thumbnail */
		preg_match('/<div class="post_thumbnail add-bottom">\n.*/', $article, $image);
		preg_match('/<img.*/', $image[0], $image);
		$image = preg_replace('/" .*/', '', $image[0]);
		$image = str_replace('<img src="', "", $image);
	
		$imgpath = str_replace(':', '', $image);
		$imgpath = str_replace('/', '', $imgpath);
		$imgpath = 'images/'.$imgpath;
	
		/* Download thumbnail into images directory */
		if (!file_exists($imgpath)) {
			file_put_contents($imgpath, file_get_contents($image));
		}

		/* Grab thumbnail caption */
		preg_match('/<div class="img_caption">.*/', $article, $caption);
		$caption = str_replace('<div class="img_caption">', "", $caption[0]);
		$caption = str_replace('</div>', "", $caption);


		/* Grab article content. Content = text + images */
		preg_match('/<p>.*<div class="addtoany/s', $article, $content);
		$content = str_replace('<div class="addtoany', "", $content[0]);
		$content = str_replace('\\', '\\\\', $content); //backslashes fuck up LaTeX
		$content = str_replace('<p>', PHP_EOL.'\noindent ', $content); //open paragraphs
		$content = str_replace('</p>', PHP_EOL.PHP_EOL, $content); //close paragraphs
		$content = html_entity_decode($content); 
		$content = str_replace("<li>", " - ", $content);
		$content = str_replace("<b>", '\textbf{', $content);
		$content = str_replace("</b>", '}', $content); //support for bold text
		$content = str_replace("<strong>", '\textbf{', $content);
		$content = str_replace("</strong>", '}', $content); //more support for bold text
		$content = str_replace("<i>", '\textit{', $content);
		$content = str_replace("</i>", '}', $content);; //support for italic text
		$content = str_replace("<em>", '\textit{', $content);
		$content = str_replace("</em>", '}', $content);; //more support for italic text
		$content = str_replace("<blockquote>", PHP_EOL.'\begin{center}'.PHP_EOL, $content);
		$content = str_replace("</blockquote>", PHP_EOL.'\end{center}'.PHP_EOL.PHP_EOL, $content); //support for blockquotes


		/* Gather in-article images */
		$contentimgs = array();
		preg_match_all('/<img.*src=".*" alt/', $content, $contentimgs);
		$aye = 0;
		while ($aye < count($contentimgs[0])) {
			$contentimgs[0][$aye] = preg_replace('/<img.*src="/', '', $contentimgs[0][$aye]);
			$contentimgs[0][$aye] = str_replace('" alt', '', $contentimgs[0][$aye]);
			$contentimgpath = str_replace('/', '', $contentimgs[0][$aye]);
			$contentimgpath = 'images/'.$contentimgpath;
			
			if (!file_exists($contentimgpath)) {
				file_put_contents($contentimgpath, file_get_contents($contentimgs[0][$aye]));
			}
			$aye = $aye + 1;
		}
		
		$content = preg_replace('/<img.*src="/', '', $content);
		$content = preg_replace('/" alt.*\/>/', PHP_EOL.PHP_EOL, $content);
		$content = preg_replace('/<figcaption.*">/', PHP_EOL.PHP_EOL.'\begin{tiny}'.PHP_EOL, $content);
		$content = str_replace("</figcaption>", PHP_EOL.'\\end{tiny}'.PHP_EOL.PHP_EOL, $content);
//		$content = preg_replace('/<.*>/', PHP_EOL.PHP_EOL, $content);
// ^ old attempt to remove remaining HTML. Turns out, PHP has a built-in function just for this:
		$content = strip_tags($content); //Now there we go!


		$content = str_replace('/', '', $content);	
		$content = str_replace('https:', PHP_EOL.'\hfill'.PHP_EOL.PHP_EOL.'\begin{center}'.PHP_EOL.'\includegraphics[width=5cm,height=5cm,keepaspectratio]{./images/https:', $content); // this is so shady
		$content = str_replace('http:', PHP_EOL.'\hfill'.PHP_EOL.PHP_EOL.'\begin{center}'.PHP_EOL.'\includegraphics[width=5cm,height=5cm,keepaspectratio]{./images/http:', $content);
		$content = str_replace('.jpg', '.jpg}'.PHP_EOL.'\\end{center}'.PHP_EOL.'\hfill'.PHP_EOL, $content);
		$content = str_replace('.jpeg', '.jpeg}'.PHP_EOL.'\\end{center}'.PHP_EOL.'\hfill', $content);
		$content = str_replace('.png', '.png}'.PHP_EOL.'\\end{center}'.PHP_EOL.'\hfill', $content);
		$content = str_replace('.gif', '.gif}'.PHP_EOL.'\\end{center}'.PHP_EOL.'\hfill', $content);

		$content = str_replace('#', '\#', $content);
		$content = str_replace('$', '\$', $content); //escaping #'s and $'s for LaTeX compatibility

//		echo "<h3>".$pw["channel"]["item"][$i]["title"]."</h3><br>".PHP_EOL;
//		echo $pw["channel"]["item"][$i]["pubDate"]." - <i>$author</i><br>".PHP_EOL;
//		echo $image."<br>".$caption;
//		echo $content;
	
//		echo "<br><br><br><br>".PHP_EOL.PHP_EOL;
//	old code from when this outputted an HTML page

		$newspaper = $newspaper."\chapter{}".PHP_EOL."\begin{Large}".PHP_EOL.$pw["channel"]["item"][$i]["title"].PHP_EOL."\\end{Large}".PHP_EOL.PHP_EOL;
		$newspaper = $newspaper."\begin{tiny}".PHP_EOL.$pw["channel"]["item"][$i]["pubDate"]." - ".$author.PHP_EOL."\\end{tiny}".PHP_EOL.PHP_EOL."\hfill".PHP_EOL.PHP_EOL;
		$newspaper = $newspaper."\includegraphics[width=10cm,height=10cm,keepaspectratio]{./".$imgpath."}".PHP_EOL.PHP_EOL;
		$newspaper = $newspaper."\begin{tiny}".PHP_EOL.$caption.PHP_EOL."\\end{tiny}".PHP_EOL.PHP_EOL;
		$newspaper = $newspaper.$content.PHP_EOL.PHP_EOL."\hfill".PHP_EOL.PHP_EOL;

		$i = $i + 1;
	
	}
	
	$newspaper = $newspaper."\\end{document}";
	
	return $newspaper;

}



//echo pullFromPW();




function pullFromCPUSA() {

	$newspaper = '\documentclass[oneside,12pt,openany]{book}
\usepackage[paperwidth=5.5in,paperheight=8.5in,total={4.8in,8.0in}]{geometry}
\pdfpageheight=8.5in
\pdfpagewidth=5.5in
\usepackage[utf8]{inputenc}
\usepackage{graphicx}
\usepackage{grffile}
\makeatletter
\def\@makechapterhead#1{%
  \vspace*{0\p@}%
  {\parindent \z@ \raggedright \normalfont
    \interlinepenalty\@M
    \Large \bfseries #1\par\nobreak
    \vskip 0\p@
  }}
\def\@makeschapterhead#1{%
  \vspace*{0\p@}%
  {\parindent \z@ \raggedright
    \normalfont
    \interlinepenalty\@M
    \Large \bfseries  #1\par\nobreak
    \vskip 0\p@
  }}
\makeatother
\begin{document}
	\begin{center}
		\null
		\vfill
		\begin{Large}
			CPUSA Weekly
		\end{Large}
	
		\includegraphics[width=10cm,height=10cm,keepaspectratio]{./cpusa-logo.png}
	
		\begin{Large}
			'.date("d F, Y").'
		\end{Large}
		\vfill
	\end{center}'.PHP_EOL.PHP_EOL;


	$cpusa = new DOMDocument();
	$cpusa->load("https://www.cpusa.org/feed/?post_type=article");
	$feed = array();
	foreach ($cpusa->getElementsByTagName('item') as $node) {
     		$item = array (
                	'title' => $node->getElementsByTagName('title')->item(0)->nodeValue,
                	'link' => $node->getElementsByTagName('link')->item(0)->nodeValue,
                	'pubDate' => $node->getElementsByTagName('pubDate')->item(0)->nodeValue,
                	'description' => $node->getElementsByTagName('description')->item(0)->nodeValue,
                	'content' => $node->getElementsByTagName('encoded')->item(0)->nodeValue

                );
        array_push($feed, $item);
	}

	$i = 0;
	while ($i < count($feed)) {
		$title = $feed[$i]["title"];
		$pubDate = $feed[$i]["pubDate"];
		$content = $feed[$i]["content"];
		
		preg_match('/src=".*" class/', $content, $image);
		$image = str_replace('src="', '', $image[0]);
		$image = str_replace('" class', '', $image);
		
		$imgpath = str_replace(':', '', $image);
		$imgpath = str_replace('/', '', $imgpath);
		$imgpath = 'images/'.$imgpath;
	
		/* Download thumbnail into images directory */
		if (!file_exists($imgpath)) {
			file_put_contents($imgpath, file_get_contents($image));
		}
		
		$content = preg_replace('/<img.*src=".*wp-post-image.*\/>/', "", $content);
		
		preg_match_all('/<img class="align.*\/>/', $content, $contentimgs);
		
		$aye = 0;
		
		while ($aye < count($contentimgs[0])) {
			$contentimgs[0][$aye] = preg_replace('/<img.*src="/', '', $contentimgs[0][$aye]);
			$contentimgs[0][$aye] = preg_replace('/".*/', '', $contentimgs[0][$aye]);
			$contentimgpath = str_replace('/', '', $contentimgs[0][$aye]);
			$contentimgpath = 'images/'.$contentimgpath;
			
			if (!file_exists($contentimgpath)) {
				file_put_contents($contentimgpath, file_get_contents($contentimgs[0][$aye]));
			}
			$aye = $aye + 1;
		}
		
		$content = preg_replace('/<img.*src="/', '', $content);
		$content = preg_replace('/" alt.*\/>/', '', $content);
		$content = str_replace('\\', '\\\\', $content); //backslashes fuck up LaTeX
		$content = str_replace('<p>', PHP_EOL.'\noindent ', $content); //open paragraphs
		$content = str_replace('</p>', PHP_EOL.PHP_EOL, $content); //close paragraphs
		$content = html_entity_decode($content); 
		$content = str_replace("<li>", " - ", $content);
		$content = str_replace("<b>", '\textbf{', $content);
		$content = str_replace("</b>", '}', $content); //support for bold text
		$content = str_replace("<strong>", '\textbf{', $content);
		$content = str_replace("</strong>", '}', $content); //more support for bold text
		$content = str_replace("<i>", '\textit{', $content);
		$content = str_replace("</i>", '}', $content);; //support for italic text
		$content = str_replace("<em>", '\textit{', $content);
		$content = str_replace("</em>", '}', $content);; //more support for italic text
		$content = str_replace("<blockquote>", PHP_EOL.'\begin{center}'.PHP_EOL, $content);
		$content = str_replace("</blockquote>", PHP_EOL.'\end{center}'.PHP_EOL.PHP_EOL, $content); //support for blockquotes
		$content = str_replace('/', '', $content);
		$content = strip_tags($content); //Removing remaining HTML

		$content = str_replace('https:', PHP_EOL.'\hfill'.PHP_EOL.PHP_EOL.'\begin{center}'.PHP_EOL.'\includegraphics[width=5cm,height=5cm,keepaspectratio]{./images/https:', $content);
		$content = str_replace('http:', PHP_EOL.'\hfill'.PHP_EOL.PHP_EOL.'\begin{center}'.PHP_EOL.'\includegraphics[width=5cm,height=5cm,keepaspectratio]{./images/http:', $content);
		$content = str_replace('.jpg', '.jpg}'.PHP_EOL.'\\end{center}'.PHP_EOL.'\hfill'.PHP_EOL, $content);
		$content = str_replace('.jpeg', '.jpeg}'.PHP_EOL.'\\end{center}'.PHP_EOL.'\hfill', $content);
		$content = str_replace('.png', '.png}'.PHP_EOL.'\\end{center}'.PHP_EOL.'\hfill', $content);
		$content = str_replace('.gif', '.gif}'.PHP_EOL.'\\end{center}'.PHP_EOL.'\hfill', $content);

		$content = str_replace('#', '\#', $content);
		$content = str_replace('$', '\$', $content);
		$content = str_replace('&', '\&', $content); //escaping #'s, $'s and &'s for LaTeX compatibility
		
		$content = str_replace('\textbf{'.PHP_EOL.'\hfill', PHP_EOL.'\hfill', $content);
		$content = str_replace('\hfill'.PHP_EOL.'}'.PHP_EOL, '\hfill'.PHP_EOL, $content);
			/* Patching something that really shouldnt be my problem
				I mean, who tries to print PICTURES in bold on an HTML page? */
		
		$newspaper = $newspaper."\chapter{}".PHP_EOL."\begin{Large}".PHP_EOL.$feed[$i]["title"].PHP_EOL."\\end{Large}".PHP_EOL.PHP_EOL;
		$newspaper = $newspaper."\begin{tiny}".PHP_EOL.$feed[$i]["pubDate"].PHP_EOL."\\end{tiny}".PHP_EOL.PHP_EOL."\hfill".PHP_EOL.PHP_EOL;
		$newspaper = $newspaper."\includegraphics[width=10cm,height=10cm,keepaspectratio]{./".$imgpath."}".PHP_EOL.PHP_EOL;
		$newspaper = $newspaper.$content.PHP_EOL.PHP_EOL."\hfill".PHP_EOL.PHP_EOL;

		$i = $i + 1;
	
	}
	
	$newspaper = $newspaper."\\end{document}";
	
	return $newspaper;

}

function pullFromGranma() {

		$newspaper = '\documentclass[oneside,12pt,openany]{book}
\usepackage[paperwidth=5.5in,paperheight=8.5in,total={4.8in,8.0in}]{geometry}
\pdfpageheight=8.5in
\pdfpagewidth=5.5in
\usepackage[utf8]{inputenc}
\usepackage{graphicx}
\usepackage{grffile}
\makeatletter
\def\@makechapterhead#1{%
  \vspace*{0\p@}%
  {\parindent \z@ \raggedright \normalfont
    \interlinepenalty\@M
    \Large \bfseries #1\par\nobreak
    \vskip 0\p@
  }}
\def\@makeschapterhead#1{%
  \vspace*{0\p@}%
  {\parindent \z@ \raggedright
    \normalfont
    \interlinepenalty\@M
    \Large \bfseries  #1\par\nobreak
    \vskip 0\p@
  }}
\makeatother
\begin{document}
	\begin{center}
		\begin{Large}
'.date("d F, Y").'
		\end{Large}
		
		\vfill
	
		\includegraphics[width=10cm,height=10cm,keepaspectratio]{./granma-logo.png}
	
		\vfill
		
	\end{center}'.PHP_EOL.PHP_EOL;
	
	$granma = new DOMDocument();
	$granma->load("http://en.granma.cu/feed");
	$feed = array();
	
	foreach ($granma->getElementsByTagName('item') as $node) {
     		$item = array (
                	'title' => $node->getElementsByTagName('title')->item(0)->nodeValue,
                	'link' => $node->getElementsByTagName('link')->item(0)->nodeValue,
                	'pubDate' => $node->getElementsByTagName('pubDate')->item(0)->nodeValue,
                	'description' => $node->getElementsByTagName('description')->item(0)->nodeValue,
                	'author' => $node->getElementsByTagName('creator')->item(0)->nodeValue

                );
        array_push($feed, $item);
	}

	$i = 0;
	while ($i < count($feed)) {
		$title = $feed[$i]["title"];
		$pubDate = $feed[$i]["pubDate"];
		$author = $feed[$i]["author"];
		$content = file_get_contents($feed[$i]["link"]);
		
		preg_match('/<div class="story.*<footer/s', $content, $content);
		$content = str_replace('<footer', "", $content[0]);
		
		preg_match('/<div class="story.*<div class="carousel /s', $content, $newcont);
		if ($newcont[0] !== null) {
			$content = str_replace('<div class="carousel "', "", $newcont[0]);
		}
		
		preg_match('/src=".*" alt/', $content, $image);
		$image = str_replace('src="', '', $image[0]);
		$image = str_replace('" alt', '', $image);
		
		if (substr($image, 0, 4) !== "http") {
			$anewprefix = "http://en.granma.cu";
			if (substr($image, 0, 1) !== "/") {
				$anewprefix = $anewprefix."/";
			}
			$image = $anewprefix.$image;
		}
		
		$imgpath = str_replace(':', '', $image);
		$imgpath = str_replace('/', '', $imgpath);
		$imgpath = 'images/'.$imgpath;
	
		/* Download thumbnail into images directory */
		if (!file_exists($imgpath)) {
			file_put_contents($imgpath, file_get_contents($image));
		}
		
		/* Grab thumbnail caption */
		preg_match('/<figcaption.*/', $content, $caption);
		$caption = preg_replace('/<figcaption.*caption">/', "", $caption[0]);
		
		$caption = strip_tags($caption);
		
		// Remove that caption from the $content variable
		
		$content = preg_replace('/<figcaption.*/', '', $content);
		
		//remove the thumbnail from $content and get remaining images
		
		$content = preg_replace('/<img.*src=".*\/>/', "", $content, 1);
		
		preg_match_all('/<img class="media.*\/>/', $content, $contentimgs);
		
		$aye = 0;
		
		$oldimglinks = array();
		
		while ($aye < count($contentimgs[0])) {
			$contentimgs[0][$aye] = preg_replace('/<img.*src="/', '', $contentimgs[0][$aye]);
			$contentimgs[0][$aye] = preg_replace('/".*/', '', $contentimgs[0][$aye]);
			
			$oldimglinks[$aye] = $contentimgs[0][$aye];
			
			if (substr($contentimgs[0][$aye], 0, 4) !== "http") {
				$anewprefix = "http://en.granma.cu";
				if (substr($contentimgs[0][$aye], 0, 1) !== "/") {
					$anewprefix = $anewprefix."/";
				}
				$contentimgs[0][$aye] = $anewprefix.$contentimgs[0][$aye];
				$content = str_replace($oldimglinks[$aye], $contentimgs[0][$aye], $content); //swap the unpatched links for the patched links to include properly
			}
			
			$contentimgpath = str_replace('/', '', $contentimgs[0][$aye]);
			$contentimgpath = 'images/'.$contentimgpath;
			
			if (!file_exists($contentimgpath)) {
				file_put_contents($contentimgpath, file_get_contents($contentimgs[0][$aye]));
			}
			$aye = $aye + 1;
		}
		
		$content = preg_replace('/<img.*src="/', '', $content);
		$content = preg_replace('/" alt.*\/>/', '', $content);
		$content = str_replace('\\', '\\\\', $content); //backslashes fuck up LaTeX
		$content = str_replace('<p>', PHP_EOL.'\noindent ', $content); //open paragraphs
		$content = str_replace('</p>', PHP_EOL.PHP_EOL, $content); //close paragraphs
		$content = html_entity_decode($content); 
		$content = str_replace("<li>", " - ", $content);
		$content = str_replace("<b>", '\textbf{', $content);
		$content = str_replace("</b>", '}', $content); //support for bold text
		$content = str_replace("<strong>", '\textbf{', $content);
		$content = str_replace("</strong>", '}', $content); //more support for bold text
		$content = str_replace("<i>", '\textit{', $content);
		$content = str_replace("</i>", '}', $content);; //support for italic text
		$content = str_replace("<em>", '\textit{', $content);
		$content = str_replace("</em>", '}', $content);; //more support for italic text
		$content = str_replace("<blockquote>", PHP_EOL.'\begin{center}'.PHP_EOL, $content);
		$content = str_replace("</blockquote>", PHP_EOL.'\end{center}'.PHP_EOL.PHP_EOL, $content); //support for blockquotes
		$content = str_replace('/', '', $content);
		$content = strip_tags($content); //Removing remaining HTML

		$content = str_replace('https:', PHP_EOL.'\hfill'.PHP_EOL.PHP_EOL.'\begin{center}'.PHP_EOL.'\includegraphics[width=5cm,height=5cm,keepaspectratio]{./images/https:', $content);
		$content = str_replace('http:', PHP_EOL.'\hfill'.PHP_EOL.PHP_EOL.'\begin{center}'.PHP_EOL.'\includegraphics[width=5cm,height=5cm,keepaspectratio]{./images/http:', $content);
		$content = str_replace('.jpg', '.jpg}'.PHP_EOL.'\\end{center}'.PHP_EOL.'\hfill'.PHP_EOL, $content);
		$content = str_replace('.jpeg', '.jpeg}'.PHP_EOL.'\\end{center}'.PHP_EOL.'\hfill', $content);
		$content = str_replace('.png', '.png}'.PHP_EOL.'\\end{center}'.PHP_EOL.'\hfill', $content);
		$content = str_replace('.gif', '.gif}'.PHP_EOL.'\\end{center}'.PHP_EOL.'\hfill', $content);

		$content = str_replace('#', '\#', $content);
		$content = str_replace('$', '\$', $content);
		$content = str_replace('&', '\&', $content); //escaping #'s, $'s and &'s for LaTeX compatibility
		
		$content = str_replace('\textbf{'.PHP_EOL.'\hfill', PHP_EOL.'\hfill', $content);
		$content = str_replace('\hfill'.PHP_EOL.'}'.PHP_EOL, '\hfill'.PHP_EOL, $content);
			/* Patching something that really shouldnt be my problem
				I mean, who tries to print PICTURES in bold on an HTML page? */
		
		$newspaper = $newspaper."\chapter{}".PHP_EOL."\begin{Large}".PHP_EOL.$feed[$i]["title"].PHP_EOL."\\end{Large}".PHP_EOL.PHP_EOL;
		$newspaper = $newspaper."\begin{tiny}".PHP_EOL.$feed[$i]["pubDate"].PHP_EOL."\\end{tiny}".PHP_EOL.PHP_EOL."\hfill".PHP_EOL.PHP_EOL;
		$newspaper = $newspaper."\includegraphics[width=10cm,height=10cm,keepaspectratio]{./".$imgpath."}".PHP_EOL.PHP_EOL;
		$newspaper = $newspaper."\begin{tiny}".PHP_EOL.$caption.PHP_EOL."\\end{tiny}".PHP_EOL.PHP_EOL;
		$newspaper = $newspaper.$content.PHP_EOL.PHP_EOL."\hfill".PHP_EOL.PHP_EOL;

		$i = $i + 1;
	
	}
	
	$newspaper = $newspaper."\\end{document}";
	
	return $newspaper;
	
}

$longopts = array("pw", "cpusa", "granma");
$shortopts = "";

$options = getopt($shortopts, $longopts);
if (isset($options["pw"])) {
	echo pullFromPW();
} else if (isset($options["cpusa"])) {
	echo pullFromCPUSA();
} else if (isset($options["granma"])) {
	echo pullFromGranma();
} else {
	die("Error: try again with --pw, --cpusa, or --granma");
}

?>