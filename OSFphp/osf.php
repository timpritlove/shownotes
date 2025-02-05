<?php

function osf_specialtags($needles, $haystack) {
  // Eine Funktion um Tags zu filtern
  $return = false;
  if (is_array($needles)) {
    foreach ($needles as $needle) {
      if (is_array($haystack) && array_search($needle, $haystack) !== false) {
        $return = true;
      }
    }
  }
  return $return;
}

function osf_affiliate_generator($url, $data) {
  // Diese Funktion wandelt Links zu Amazon, Thomann und iTunes in Affiliate Links um
  $amazon     = $data['amazon'];
  $thomann    = $data['thomann'];
  $tradedoubler = $data['tradedoubler'];

  if ((strstr($url, 'www.amazon.de/') && strstr($url, 'p/')) && ($amazon != '')) {
    if (strstr($url, "dp/")) {
      $pid = substr(strstr($url, "dp/"), 3, 10);
    } elseif (strstr($url, "gp/product/")) {
      $pid = substr(strstr($url, "gp/product/"), 11, 10);
    } else {
      $pid = '';
    }
    $aid  = '?ie=UTF8&amp;linkCode=as2&amp;tag=' . $amazon;
    $purl = 'https://www.amazon.de/gp/product/' . $pid . '/' . $aid;
  } elseif ((strstr($url, 'www.amazon.com/') && strstr($url, 'p/')) && ($amazon != '')) {
    if (strstr($url, "dp/")) {
      $pid = substr(strstr($url, "dp/"), 3, 10);
    } elseif (strstr($url, "gp/product/")) {
      $pid = substr(strstr($url, "gp/product/"), 11, 10);
    } else {
      $pid = '';
    }
    $aid  = '?ie=UTF8&linkCode=as2&amp;tag=' . $amazon;
    $purl = 'https://www.amazon.com/gp/product/' . $pid . '/' . $aid;
  } elseif ((strstr($url, 'thomann.de/de/')) && ($thomann != '')) {
    $thomannurl = explode('.de/', $url);
    $purl     = 'https://www.thomann.de/index.html?partner_id=' . $thomann . '&amp;page=/' . $thomannurl[1];
  } elseif ((strstr($url, 'itunes.apple.com/de')) && ($tradedoubler != '')) {
    if (strstr($url, '?')) {
      $purl = 'http://clkde.Tradedoubler.com/click?p=23761&amp;a=' . $tradedoubler . '&amp;url=' . urlencode($url . '&amp;partnerId=2003');
    } else {
      $purl = 'http://clkde.Tradedoubler.com/click?p=23761&amp;a=' . $tradedoubler . '&amp;url=' . urlencode($url . '?partnerId=2003');
    }
  } else {
    $purl = $url;
  }
  $purl = str_ireplace(array('http://de.wikipedia.org/', 'http://en.wikipedia.org/', 'http://github.com', 'http://www.torproject.org/', 'http://www.eff.org/', 'http://thepiratebay.se/', 'http://blog.fefe.de/', 'http://netzpolitik.org/', 'http://www.piratenpartei.de/', 'http://twitter.com/', 'http://www.facebook.com/', 'http://alpha.app.net/'), array('https://de.wikipedia.org/', 'https://en.wikipedia.org/', 'https://github.com', 'https://www.torproject.org/', 'https://www.eff.org/', 'https://thepiratebay.se/', 'https://blog.fefe.de/', 'https://netzpolitik.org/', 'https://www.piratenpartei.de/', 'https://twitter.com/', 'https://www.facebook.com/', 'https://alpha.app.net/'), $purl);
  return $purl;
}

function osf_convert_time($string) {
  // Diese Funktion wandelt Zeitangaben vom Format 01:23:45 (H:i:s) in Sekundenangaben um
  $strarray = explode(':', $string);

  // Sicherstellen, dass das Array die richtige Anzahl von Elementen enthält
  if (count($strarray) == 3) {
      // Validierung der einzelnen Teile als Zahlen
      if (is_numeric($strarray[0]) && is_numeric($strarray[1]) && is_numeric($strarray[2])) {
          return (($strarray[0] * 3600) + ($strarray[1] * 60) + $strarray[2]);
      }
  } elseif (count($strarray) == 2) {
      if (is_numeric($strarray[0]) && is_numeric($strarray[1])) {
          return (($strarray[0] * 60) + $strarray[1]);
      }
  }

  // Falls die Eingabe ungültig ist, gib 0 oder einen Standardwert zurück
  return 0;
}

function osf_time_from_timestamp($utimestamp) {
  // Diese Funktion wandelt Zeitangaben im UNIX-Timestamp Format in relative Zeitangaben im Format 01:23:45 um
  global $osf_starttime;
  if (strpos($utimestamp, ':') != false) {
    $pause = explode(':', $utimestamp);
    $osf_starttime = $osf_starttime + $pause[1] - $pause[0];
  }
  $duration = $utimestamp - $osf_starttime;
  $sec = $duration % 60;
  if ($sec < 10) {
    $sec = '0' . $sec;
  }
  $min = $duration / 60 % 60;
  if ($min < 10) {
    $min = '0' . $min;
  }
  $hour = $duration / 3600 % 24;
  if ($hour < 10) {
    $hour = '0' . $hour;
  }
  return "\n" . $hour . ':' . $min . ':' . $sec;
}

function osf_replace_timestamps($shownotes) {
  // Durchsucht die Shownotes nach Zeitangaben (UNIX-Timestamp) und übergibt sie an die Funktion osf_time_from_timestamp()
  global $osf_starttime;
  preg_match_all('/\n[0-9]{9,15}/', $shownotes, $unixtimestamps);
  if (isset($unixtimestamps)) {
    if (isset($unixtimestamps[0])) {
      if (isset($unixtimestamps[0][0])) {
        $osf_starttime = $unixtimestamps[0][0];
        $regexTS = array(
          '/\n[0-9:]{9,23}/',
          'osf_time_from_timestamp(\'\\0\')'
        );
        return preg_replace_callback($regexTS[0], function ($matches) {
          return osf_time_from_timestamp($matches[0]);
        }, $shownotes);
      }
    }
  }
  return $shownotes;
}

function osf_parse_person($string) {
  $profileurl = false;
  $name = '';
  $urlmatch = preg_match_all('/\<(http[\S]+)\>/', $string, $url);
  if ($urlmatch != 0 && $urlmatch != false) {
    $profileurl = $url[1][0];
    $name = trim(preg_replace('/\<(http[\S]+)\>/', '', $string));
  } else {
    if ((strpos($string, ' (@') != false)&&(strpos($string, '@:adn') != false)) {
      preg_match_all('/([^\(]+)\(@([^\)]+)@:adn\)/', $string, $url);
      $profileurl = 'https://alpha.app.net/' . trim($url[2][0]);
      $name = trim($url[1][0]);
    } elseif ((strpos($string, ' (@') != false)&&(strpos($string, '@:gh') != false)) {
      preg_match_all('/([^\(]+)\(@([^\)]+)@:gh\)/', $string, $url);
      $profileurl = 'https://github.com/' . trim($url[2][0]);
      $name = trim($url[1][0]);
    } elseif (strpos($string, ' (@') != false) {
      preg_match_all('/([^\(]+)\(@([^\)]+)\)/', $string, $url);
      $profileurl = 'https://twitter.com/' . trim($url[2][0]);
      $name = trim($url[1][0]);
    } elseif (strpos($string, '@:adn') != false) {
      preg_match_all('/@(\(?[\S]+\)?)@:adn/', $string, $url);
      $profileurl = 'https://alpha.app.net/' . trim($url[1][0]);
      $name = trim($url[1][0]);
    } elseif (strpos($string, '@:gh') != false) {
      preg_match_all('/@(\(?[\S]+\)?)@:gh/', $string, $url);
      $profileurl = 'https://github.com/' . trim($url[1][0]);
      $name = trim($url[1][0]);
    } elseif (strpos($string, '@') != false) {
      preg_match_all('/@(\(?[\S]+\)?)/', $string, $url);
      $profileurl = 'https://twitter.com/' . trim($url[1][0]);
      $name = trim($url[1][0]);
    } else {
      $name = trim($string);
    }
  }
  $return['url']  = $profileurl;
  $return['name'] = trim($name, ' :;()"');
  return $return;
}

function osf_get_persons($persons, $header) {
  $regex['shownoter'] = '/(Shownoter|Zusammengetragen)[^:]*:([ \S]*)/';
  $regex['podcaster'] = '/(Podcaster|Zusammengetragen)[^:]*:([ \S]*)/';
  preg_match_all($regex[$persons], $header, $persons);
  $persons    = preg_split('/(,|;| und | and )/', @$persons[2][0]);
  $personsArray = array();
  $personsArrayHTML = array();
  $i = 0;
  foreach ($persons as $person) {
    $personArray = osf_parse_person($person);
    if ($personArray['url'] == false) {
      $personsArray[$i]['name'] = trim($personArray['name']);
      $personsArrayHTML[$i] = '<span>' . $personsArray[$i]['name'] . '</span>';
    } else {
      $personsArray[$i]['name'] = trim($personArray['name']);
      $personsArray[$i]['url'] = trim($personArray['url'], " \-\)\(\<\>\t\n\r\0\x0B");
      $personsArrayHTML[$i] = '<a target="_blank" href="' . $personsArray[$i]['url'] . '">' . $personsArray[$i]['name'] . '</a>';
    }
    $i++;
  }
  return array('html' => implode(', ', $personsArrayHTML), 'data' => $personsArray);
}

function osf_get_episodetime($header) {
  $regex = '/(Starttime|Startime|Startzeit|Episodenbeginn|Episodetime|Episodestart)[^:]*:([ \S]*)/';
  preg_match_all($regex, $header, $date);
  date_default_timezone_set('Europe/Berlin');
  $date = strtotime(@$date[2][0]);
  if($date != false) {
    return $date;
  }
  return '0';
}

function osf_get_podcastname($header) {
  $regex = '/(Podcast|Podcastname|Sendung)[^:]*:([ \S]*)/';
  preg_match_all($regex, $header, $title);
  return @$title[2][0];
}

function osf_get_episodenumber($header) {
  $regex = '/(Episode|Folge)[^:]*:([ \S]*)/';
  preg_match_all($regex, $header, $number);
  return @$number[2][0];
}

function osf_get_episodename($header) {
  $regex = '/(Title|Episodetitle|Thema|Subject)[^:]*:([ \S]*)/i';
  preg_match_all($regex, $header, $title);
  return @$title[2][0];
}

function osf_parser($shownotes, $data) {
  // Diese Funktion ist das Herzstück des OSF-Parsers
  $tagsmode  = @$data['tagsmode'];
  $specialtags = $data['tags'];
  $exportall   = $data['fullmode'];

  // entferne alle Angaben vorm und im Header
  $splitAt = false;
  if (strpos($shownotes, '/HEADER')) {
    $splitAt = '/HEADER';
  } elseif (strpos($shownotes, '/HEAD')) {
    $splitAt = '/HEAD';
  }

  if ($splitAt != false) {
    $shownotes = explode($splitAt, $shownotes, 2);
  } else {
    $shownotes = preg_split("/(\n\s*\n)/", $shownotes, 2);
  }
  if (count($shownotes) != 1) {
    $header  = $shownotes[0];
    $shownotes = $shownotes[1];
  } else {
    $shownotes = $shownotes[0];
  }

  // wandle Zeitangaben im UNIX-Timestamp Format in relative Zeitangaben im Format 01:23:45 um
  $shownotes = "\n" . osf_replace_timestamps("\n" . $shownotes);

  // zuerst werden die regex-Definitionen zum erkennen von Zeilen, Tags, URLs und subitems definiert
  $pattern['zeilen']  = '/(((\d+:)?\d+:\d+)(\\.\d+)?)*(.+)/';
  $pattern['tags']    = '((\s#)(\S*))';
  $pattern['urls']    = '(\s+((http(|s)://\S{0,256})\s))';
  $pattern['urls2']   = '(\<((http(|s)://\S{0,256})>))';
  $pattern['kaskade'] = '/(((((\d+:)?\d+:\d+)(\\.\d+)?)|(\d{9,}))*[\t ]* *[\-\–\—]+ )/';

  // danach werden mittels des zeilen-Patterns die Shownotes in Zeilen/items geteilt
  preg_match_all($pattern['zeilen'], $shownotes, $zeilen, PREG_SET_ORDER);

  // Zählvariablen definieren
  // i = item, lastroot = Nummer des letzten Hauptitems, kaskadei = Verschachtelungstiefe
  $i        = 0;
  $lastroot = 0;
  $kaskadei = 0;
  $returnarray['info']['zeilen'] = 0;

  // Zeile für Zeile durch die Shownotes gehen
  foreach ($zeilen as $zeile) {
    // Alle Daten der letzten Zeile verwerfen
    unset($newarray);

    // Text der Zeile in Variable abspeichern und abschließendes Leerzeichen anhängen
    $text = $zeile[5] . ' ';

    // Mittels regex tags und urls extrahieren
    preg_match_all($pattern['tags'], $text, $tags, PREG_PATTERN_ORDER);
    preg_match_all($pattern['urls'], $text, $urls, PREG_PATTERN_ORDER);
    preg_match_all($pattern['urls2'], $text, $urls2, PREG_PATTERN_ORDER);
    preg_match_all($pattern['kaskade'], $text, $kaskade, PREG_PATTERN_ORDER);
    if (isset($kaskade[0][0])) {
      $kaskade = strlen(trim($kaskade[0][0]));
    } else {
      $kaskade = 0;
    }

    // array mit URLs im format <url> mit array mit URLs im format  url  zusammenführen
    $urls = array_merge($urls[2], $urls2[2]);

    // Zeit und Text in Array zur weitergabe speichern
    $newarray['time'] = $zeile[1];
    $regex['search']  = array(
      '/\s&quot;/',
      '/\s"/',
      '/&quot;\s/',
      '/"\s/',
      '/(\S)-(\S)/',
      '/\x27/'
    );
    $regex['replace'] = array(
      ' &#8222;',
      ' &#8222;',
      '&#8221; ',
      '&#8221; ',
      "$1&#8209;$2",
      '&#39;'
    );

    $newarray['orig'] = trim(preg_replace(array(
      $pattern['tags'],
      $pattern['urls'],
      $pattern['urls2']
    ), array(
      '',
      '',
      ''
    ), $zeile[5]));

    $newarray['text'] = trim(htmlentities($newarray['orig'], ENT_QUOTES, 'UTF-8'));

    $newarray['rank'] = $kaskade;

    // Wenn Tags vorhanden sind, diese ebenfalls im Array speichern
    $newarray['chapter'] = false;
    if (count($tags[2]) > 0) {
      foreach ($tags[2] as $tag) {
        if (strlen($tag) === 1) {
          switch (strtolower($tag)) {
            case 'c':
              $newarray['tags'][] = 'chapter';
              break;
            case 'g':
              $newarray['tags'][] = 'glossary';
              break;
            case 'l':
              $newarray['tags'][] = 'link';
              break;
            case 's':
              $newarray['tags'][] = 'section'; // niemand verwendet section, wir sollten #s für shopping freigeben
              break;
            case 't':
              $newarray['tags'][] = 'topic';
              break;
            case 'v':
              $newarray['tags'][] = 'video';
              break;
            case 'a':
              $newarray['tags'][] = 'audio';
              break;
            case 'q':
              $newarray['tags'][] = 'quote';
              break;
            case 'i':
              $newarray['tags'][] = 'image';
              break;
            case 'r':
              $newarray['tags'][] = 'revision';
              break;
            case 'p':
              $newarray['tags'][] = 'prediction';
              break;
          }
        } else {
          $newarray['tags'][] = strtolower($tag);
        }
      }
      if (isset($newarray['tags'])) {
        if (in_array('chapter', $newarray['tags'])) {
          $newarray['chapter'] = true;
        }
      }
    }

    // Wenn URLs vorhanden sind, auch diese im Array speichern
    if (count($urls) > 0) {
      $purls = array();
      foreach ($urls as $url) {
        $purls[] = osf_affiliate_generator($url, $data);
      }
      $newarray['urls'] = $purls;
    }

    $skipskipskip = false;
    if(isset($newarray['tags'])) {
      if(array_search('revision', $newarray['tags']) !== false) {
        $skipskipskip = true;
      }
    }
    //echo array_search('revision', $newarray['tags']);
    // Speichere nur Zeilen die nicht zur Revision/Überarbeitung markiert wurden
    if ($skipskipskip === false) {
      // Wenn Zeile mit "- " beginnt im Ausgabe-Array verschachteln
      if (!$newarray['chapter']) {
        if (isset($newarray['tags'])) {
          if (((osf_specialtags($newarray['tags'], $specialtags)) && ($tagsmode == 0)) || ($tagsmode == 1) || ($exportall == 'true')) {
            if (preg_match($pattern['kaskade'], $zeile[0])) {
              $newarray['subtext'] = true;
              $returnarray['export'][$lastroot]['subitems'][$kaskadei] = $newarray;
            } else {
              $returnarray['export'][$lastroot]['subitems'][$kaskadei] = $newarray;
            }
          } else {
            unset($newarray);
          }
        } elseif ($exportall == 'true') {
          if (preg_match($pattern['kaskade'], $zeile[0])) {
            $newarray['subtext'] = true;
            $returnarray['export'][$lastroot]['subitems'][$kaskadei] = $newarray;
          } else {
            $returnarray['export'][$lastroot]['subitems'][$kaskadei] = $newarray;
          }
        }
        // Verschachtelungstiefe hochzählen
        ++$kaskadei;
      }
      
      // Wenn die Zeile keine Verschachtelung darstellt
      else {
        if (((osf_specialtags($newarray['tags'], $specialtags)) && ($tagsmode == 0)) || ((!osf_specialtags($newarray['tags'], $specialtags)) && ($tagsmode == 1)) || ($exportall == 'true')) {
          // Daten auf oberster ebene einfügen
          $returnarray['export'][$i] = $newarray;
      
          // Nummer des letzten Objekts auf oberster ebene auf akutelle Item Nummer setzen
          $lastroot = $i;
      
          // Verschachtelungstiefe auf 0 setzen
          $kaskadei = 0;
        } else {
          unset($newarray);
        }
      }
    }
    // Item Nummer hochzählen
    ++$i;
  }

  // Zusatzinformationen im Array abspeichern (Zeilenzahl, Zeichenlänge und Hash der Shownotes)
  $returnarray['info']['zeilen']  = $i;
  $returnarray['info']['zeichen'] = strlen($shownotes);
  $returnarray['info']['hash']    = md5($shownotes);
  if (isset($header)) {
    $returnarray['header'] = $header;
  }
  // Rückgabe der geparsten Daten
  return $returnarray;
}

function osf_checktags($needles, $haystack) {
  $return = false;
  if (is_array($haystack)) {
    foreach ($needles as $needle) {
      if (array_search($needle, $haystack) !== false) {
        $return = true;
      }
    }
  }

  return $return;
}

function osf_item_textgen($subitem, $tagtext, $text, $template = 'block style') {
  global $shownotes_options;

  // Default values for options
  $delimiter = $shownotes_options['main_delimiter'] ?? ' &nbsp;';
  if ($template === 'list style') {
      $delimiter = '';
  }

  if (trim($text) === "") {
      return ''; // Return early if the text is empty
  }

  // Build the title with time and text
  $title = '';
  if (!empty($subitem['time'])) {
      $title .= htmlspecialchars(trim($subitem['time']), ENT_QUOTES, 'UTF-8') . ': ';
  }
  $title .= htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');

  // Add tags to title and tagtext if they exist
  if (!empty($subitem['tags']) && is_array($subitem['tags'])) {
      $title .= ' (' . implode(' ', $subitem['tags']) . ')';
      $tagtext .= ' osf_' . implode(' osf_', $subitem['tags']);
  }

  // Handle tag decoration based on options
  if (isset($shownotes_options['main_tagdecoration'])) {
      if (empty($subitem['tags'])) {
          $text = '<small>' . htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8') . '</small>';
      } elseif (in_array('topic', $subitem['tags'])) {
          $text = '<strong>' . htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8') . '</strong>';
      } elseif (in_array('quote', $subitem['tags'])) {
          $text = '<em>' . htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8') . '</em>';
      } elseif (count($subitem['tags']) === 0) {
          $text = '<small>' . htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8') . '</small>';
      } else {
          $text = htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
      }
  } else {
      $text = htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
  }

  // Process URLs
  $subtext = '';
  if (!empty($subitem['urls']) && is_array($subitem['urls']) && isset($subitem['urls'][0])) {
      $url = htmlspecialchars($subitem['urls'][0], ENT_QUOTES, 'UTF-8');
      $tagtext .= ' osf_url';
      if (strpos($url, 'https://') !== false) {
          $tagtext .= ' osf_https';
      }

      // Extract domain for additional tagging
      $url_parts = parse_url($url);
      if (isset($url_parts['host'])) {
          $host_parts = explode('.', $url_parts['host']);
          $domain_tag = 'osf_' . preg_replace('/[^a-zA-Z0-9]/', '', $host_parts[count($host_parts) - 2] ?? '') .
              ($host_parts[count($host_parts) - 1] ?? '');
          $tagtext .= ' ' . $domain_tag;
      }

      // Build the link with appropriate class
      $subtext .= '<a target="_blank" title="' . $title . '" href="' . $url . '" class="' . $tagtext . '">' . $text . '</a>';
  } else {
      // If no URL, just return the text wrapped in a span
      $subtext .= '<span title="' . $title . '"';
      if (!empty($tagtext)) {
          $subtext .= ' class="' . $tagtext . '"';
      }
      $subtext .= '>' . $text . '</span>';
  }

  // Append the delimiter
  $subtext .= $delimiter;

  return $subtext;
}
function osf_feed_textgen($subitem, $tagtext, $text) {
  global $shownotes_options;

  // Sicherstellen, dass ein gültiger Delimiter gesetzt ist
  $delimiter = $shownotes_options['main_delimiter'] ?? ' &nbsp;';
  if (trim($text) == "") {
      return '';
  }

  // Titel generieren
  $title = '';
  if (!empty($subitem['time'])) {
      $time = trim($subitem['time']);
      if ($time !== "") {
          $title .= $subitem['time'] . ': ';
      }
  }
  $title .= $text;

  $subtext = '';

  // URLs verarbeiten, wenn sie vorhanden sind
  if (!empty($subitem['urls']) && is_array($subitem['urls']) && isset($subitem['urls'][0])) {
      $url = parse_url($subitem['urls'][0]);
      if ($url !== false && isset($url['host'])) {
          $host_parts = explode('.', $url['host']);
          $subtext .= '<a href="' . htmlspecialchars($subitem['urls'][0], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8') . '</a>';
      } else {
          // URL ist ungültig
          $subtext .= '<span>' . htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8') . '</span>';
      }
  } else {
      // Keine URLs vorhanden
      $subtext .= '<span>' . htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8') . '</span>';
  }
  $subtext .= $delimiter;

  return $subtext;
}


function osf_metacast_textgen($subitem, $tagtext, $text) {
  global $shownotes_options;
  $delimiter = ' ';
  if (trim($text) == "") {
    return '';
  }

  $title = '';
  if (isset($subitem['time'])) {
    $time = trim($subitem['time']);
    if ($time !== "") {
      $title .= $subitem['time'] . ': ';
    }
  }
  $title .= $text;
  if (isset($subitem['tags'])) {
    $title .= ' (' . implode(' ', $subitem['tags']) . ')';
    $tagtext .= ' osf_' . implode(' osf_', $subitem['tags']);
  }

  $subtext = '';
  if (strlen($text) > 82) {
    $splittext = explode("\n", wordwrap($text, 70));
    $splittext = $splittext[0] . '&#8230;';
  } else {
    $splittext = $text;
  }

  if (isset($subitem['urls'][0])) {
    $tagtext .= ' osf_url';
    if (strpos($subitem['urls'][0], 'https://') !== false) {
      $tagtext .= ' osf_https';
    }
    $url = parse_url($subitem['urls'][0]);
    $url = explode('.', $url['host']);
    $count = count($url);
    $tagtext .= ' osf_' . $url[$count-2] . $url[$count-1];
    $subtext .= '<a target="_blank" title="' . $title . '" href="' . $subitem['urls'][0] . '"';
    if (strstr($subitem['urls'][0], 'wikipedia.org/wiki/')) {
      $subtext .= ' class="osf_wiki ' . $tagtext . '"';
    } elseif (strstr($subitem['urls'][0], 'www.amazon.')) {
      $subtext .= ' class="osf_amazon ' . $tagtext . '"';
    } elseif (strstr($subitem['urls'][0], 'www.youtube.com/') || strstr($subitem['urls'][0], 'www.youtu.be/') || ($subitem['chapter'] == 'video')) {
      $subtext .= ' class="osf_youtube ' . $tagtext . '"';
    } elseif (strstr($subitem['urls'][0], 'flattr.com/')) {
      $subtext .= ' class="osf_flattr ' . $tagtext . '"';
    } elseif (strstr($subitem['urls'][0], 'twitter.com/')) {
      $subtext .= ' class="osf_twitter ' . $tagtext . '"';
    } elseif (strstr($subitem['urls'][0], 'app.net/')) {
      $subtext .= ' class="osf_appnet ' . $tagtext . '"';
    } else {
      $subtext .= ' class="' . $tagtext . '"';
    }

    if ((isset($subitem['time'])) && (trim($subitem['time']) != '')) {
      $subtext .= '>' . $splittext . '<div><span class="osf_timebutton">' . $subitem['time'] . '</span></div></a></li>' . " ";
    } else {
      $subtext .= '>' . $splittext . '</a>';
    }
  } else {
    $subtext .= '<span title="' . $title . '"';
    if ($tagtext != '') {
      $subtext .= ' class="' . $tagtext . '"';
    }
    if ((isset($subitem['time'])) && (trim($subitem['time']) != '')) {
      $subtext .= '>' . $splittext . '<div><span class="osf_timebutton">' . $subitem['time'] . '</span></div></span>';
    } else {
      $subtext .= '>' . $splittext . '</span>';
    }
  }
  $subtext .= $delimiter;

  return $subtext;
}

//HTML export im anyca.st style
function osf_export_block($array, $full = false, $template, $filtertags = array(0 => 'spoiler')) {
  global $shownotes_options;
  $delimiter = $shownotes_options['main_delimiter'] ?? ' &nbsp;';
  $lastdelimiter = $shownotes_options['main_last_delimiter'] ?? '. ';
  $usnid     = get_the_ID() . '_' . str_replace(' ', '', $template);
  $returnstring  = '<div id="osf_usnid_' . $usnid . '">';
  $filterpattern = array(
    '(\s(#)(\S*))',
    '(\<((http(|s)://[\S#?-]{0,128})>))',
    '(\s+((http(|s)://[\S#?-]{0,128})\s))',
    '(^ *[\-\–\—]*)'
  );
  $arraykeys   = array_keys($array);
  for ($i = 0; $i <= count($array); $i++) {
    if (is_array($array) && isset($array[$arraykeys[0]])) {
      if (isset($arraykeys[$i])) {
        if (isset($array[$arraykeys[$i]])) {
          if (!isset($array[$arraykeys[$i]]['time'])) {
            $array[$arraykeys[$i]]['time'] = '';
          }
          if (!isset($array[$arraykeys[$i]]['chapter'])) {
            $array[$arraykeys[$i]]['chapter'] = false;
          }
          if (!isset($array[$arraykeys[$i]]['text'])) {
            $array[$arraykeys[$i]]['text'] = '';
          }
          if (!isset($array[$arraykeys[$i]]['rank'])) {
            $array[$arraykeys[$i]]['rank'] = 0;
          }
          if ($array[$arraykeys[$i]]['chapter'] || ((($full != false) && ($array[$arraykeys[$i]]['time'] != ''))) || ($i == 0)) {
            $text = preg_replace($filterpattern, '', $array[$arraykeys[$i]]['text']);
            if (strpos($array[$arraykeys[$i]]['time'], '.')) {
              $time = explode('.', $array[$arraykeys[$i]]['time']);
              $time = $time[0];
            } else {
              $time = $array[$arraykeys[$i]]['time'];
            }

            $returnstring .= "\n" . '<div class="osf_chapterbox"> ';
            if (isset($array[$arraykeys[$i]]['urls'][0])) {
              $returnstring .= ' <h'.($array[$arraykeys[$i]]['rank']+2);
              if ($array[$arraykeys[$i]]['chapter']) {
                $returnstring .= ' class="osf_chapter"';
              }
              $returnstring .= '><a target="_blank" href="' . $array[$arraykeys[$i]]['urls'][0] . '">' . $text . '</a></strong> <span class="osf_chaptertime" data-time="' . osf_convert_time($time) . '">' . $time . '</h'.($array[$arraykeys[$i]]['rank']+2).'><div class="osf_items"> ' . "\n";
            } else {
              $returnstring .= ' <h'.($array[$arraykeys[$i]]['rank']+2);
              if ($array[$arraykeys[$i]]['chapter']) {
                $returnstring .= ' class="osf_chapter"';
              }
              $returnstring .= '>' . $text . '</h'.($array[$arraykeys[$i]]['rank']+2).'> <span class="osf_chaptertime" data-time="' . osf_convert_time($time) . '">' . $time . '</span><p class="osf_items"> 
</p></div>';
            }

            if (isset($array[$arraykeys[$i]]['subitems'])) {
              for ($ii = 0; $ii <= count($array[$arraykeys[$i]]['subitems'], COUNT_RECURSIVE); $ii++) {
                if (isset($array[$arraykeys[$i]]['subitems'][$ii])) {
                  if ((((($full != false) || (!$array[$arraykeys[$i]]['subitems'][$ii]['subtext'])) && ((($full == 1) && (!osf_checktags($filtertags, @$array[$arraykeys[$i]]['subitems'][$ii]['tags']))) || ($full == 2))) && (strlen(trim($array[$arraykeys[$i]]['subitems'][$ii]['orig'])) > 2)) || ($full == 2)) {
                    if (($full == 2) && (@osf_checktags($filtertags, @$array[$arraykeys[$i]]['subitems'][$ii]['tags']))) {
                      $tagtext = ' osf_spoiler';
                    } else {
                      $tagtext = '';
                    }
                    $substart = '';
                    $subend   = '';
                    if (isset($array[$arraykeys[$i]]['subitems'][$ii]['subtext'])) {
                      if ($array[$arraykeys[$i]]['subitems'][$ii]['subtext']) {
                        if ((@$array[$arraykeys[$i]]['subitems'][$ii - 1]['rank']) < (@$array[$arraykeys[$i]]['subitems'][$ii]['rank'])) {
                          $substart = '(';
                        }
                        if ((@$array[$arraykeys[$i]]['subitems'][$ii + 1]['rank']) < (@$array[$arraykeys[$i]]['subitems'][$ii]['rank'])) {
                          $subend = ')' . $delimiter;
                        }
                      }
                    }
                    $text = preg_replace($filterpattern, '', $array[$arraykeys[$i]]['subitems'][$ii]['text']);
                    if (is_feed()) {
                      $subtext = osf_feed_textgen($array[$arraykeys[$i]]['subitems'][$ii], $tagtext, $text);
                    } else {
                      if ($template == 'block style') {
                        $subtext = osf_item_textgen($array[$arraykeys[$i]]['subitems'][$ii], $tagtext, $text);
                      } elseif ($template == 'button style') {
                        $subtext = osf_metacast_textgen($array[$arraykeys[$i]]['subitems'][$ii], $tagtext, $text);
                      }
                    }
                    $returnstring .= $substart . $subtext . $subend;
                  }
                }
              }
            }
            $returnstring .= '</p></div>';
          }
        }
      }
    }
  }

  $returnstring .= '</div>' . "\n";
  $cleanupsearch = array(
    $delimiter . '</div>',
    $delimiter . '</p>',
    ',</div>',
    $delimiter . ')',
    $delimiter . '(',
    'osf_"',
    'osf_ ',
    '<div class="osf_chapterbox">  <h2></h2> <span class="osf_chaptertime" data-time=""></span><p class="osf_items"> 
</p></div>'
  );

  $cleanupreplace = array(
    $lastdelimiter . '</div>',
    $lastdelimiter . '</p>',
    '</div>',
    ') ',
    ' (',
    ' ',
    ' ',
    ' '
  );

  if (($template == 'button style') && (!is_feed())) {
    $returnstring .= '<script>osf_init("' . $usnid . '", "button");</script>';
  }
  $returnstring = str_replace($cleanupsearch, $cleanupreplace, $returnstring);
  return $returnstring;
}

function osf_export_list($array, $full = false, $template, $filtertags = array(0 => 'spoiler')) {
  global $shownotes_options;
  if (isset($shownotes_options['main_delimiter'])) {
    $delimiter = $shownotes_options['main_delimiter'];
  } else {
    $delimiter = ' &nbsp;';
  }
  if (isset($shownotes_options['main_last_delimiter'])) {
    $lastdelimiter = $shownotes_options['main_last_delimiter'];
  } else {
    $lastdelimiter = '. ';
  }
  $delimiter   = '';
  $lastdelimiter = '';

  $usnid     = get_the_ID() . '_' . str_replace(' ', '', $template);
  $returnstring  = '<div id="osf_usnid_' . $usnid . '">';
  $filterpattern = array(
    '(\s(#)(\S*))',
    '(\<((http(|s)://[\S#?-]{0,128})>))',
    '(\s+((http(|s)://[\S#?-]{0,128})\s))',
    '(^ *[\-\–\—]*)'
  );

  if (is_array($array)) {
    $arraykeys = array_keys($array);
  } else {
      $arraykeys = []; // Fallback für den Fall, dass $array null oder kein Array ist
  }

  for ($i = 0; $i <= count($array); $i++) {
    if (isset($array[$arraykeys[0]])) {
      if (isset($arraykeys[$i])) {
        if (isset($array[$arraykeys[$i]])) {
          if ((@$array[$arraykeys[$i]]['chapter']) || ($i == 0)) {
            $text = preg_replace($filterpattern, '', @$array[$arraykeys[$i]]['text']);
            if (strpos(@$array[$arraykeys[$i]]['time'], '.')) {
              $time = explode('.', $array[$arraykeys[$i]]['time']);
              $time = $time[0];
            } else {
              $time = @$array[$arraykeys[$i]]['time'];
            }

            $returnstring .= "\n" . '<div class="osf_chapterbox"> ';
            if (isset($array[$arraykeys[$i]]['urls'][0])) {
              $returnstring .= ' <h'.(@$array[$arraykeys[$i]]['rank']+2);
              if ($array[$arraykeys[$i]]['chapter']) {
                $returnstring .= ' class="osf_chapter"';
              }
              $returnstring .= '><a target="_blank" href="' . $array[$arraykeys[$i]]['urls'][0] . '">' . $text . '</a></h'.(@$array[$arraykeys[$i]]['rank']+2).'><span class="osf_chaptertime" data-time="' . osf_convert_time($time) . '">' . $time . '</span><div class="osf_items"> ' . "\n";
            } else {
              $returnstring .= ' <h'.(@$array[$arraykeys[$i]]['rank']+2);
              if (@$array[$arraykeys[$i]]['chapter']) {
                $returnstring .= ' class="osf_chapter"';
              }
              $returnstring .= '>' . $text . '</h'.(@$array[$arraykeys[$i]]['rank']+2).'><span class="osf_chaptertime" data-time="' . osf_convert_time($time) . '">' . $time . '</span><ul class="osf_items"> ' . "\n";
            }

            if (isset($array[$arraykeys[$i]]['subitems'])) {
              for ($ii = 0; $ii <= count($array[$arraykeys[$i]]['subitems'], COUNT_RECURSIVE); $ii++) {
                if (isset($array[$arraykeys[$i]]['subitems'][$ii])) {
                  if ((((($full != false) || (!$array[$arraykeys[$i]]['subitems'][$ii]['subtext'])) && ((($full == 1) && (!osf_checktags($filtertags, @$array[$arraykeys[$i]]['subitems'][$ii]['tags']))) || ($full == 2))) && (strlen(trim($array[$arraykeys[$i]]['subitems'][$ii]['orig'])) > 2)) || ($full == 2)) {
                    if (($full == 2) && (@osf_checktags($filtertags, @$array[$arraykeys[$i]]['subitems'][$ii]['tags']))) {
                      $tagtext = ' osf_spoiler';
                    } else {
                      $tagtext = '';
                    }
                    $substart = '';
                    $subend   = '';
                    if (isset($array[$arraykeys[$i]]['subitems'][$ii]['subtext'])) {
                      if ($array[$arraykeys[$i]]['subitems'][$ii]['subtext']) {
                        if ((@$array[$arraykeys[$i]]['subitems'][$ii - 1]['rank']) < (@$array[$arraykeys[$i]]['subitems'][$ii]['rank'])) {
                          $substart = '<ul class="osf_rank'.$array[$arraykeys[$i]]['subitems'][$ii]['rank'].'">';
                        }
                        if ((@$array[$arraykeys[$i]]['subitems'][$ii + 1]['rank']) < (@$array[$arraykeys[$i]]['subitems'][$ii]['rank'])) {
                          $subend = '</ul>' . $delimiter;
                        }
                      }
                    }
                    $text = preg_replace($filterpattern, '', $array[$arraykeys[$i]]['subitems'][$ii]['text']);
                    if (is_feed()) {
                      $subtext = '<li>' . osf_feed_textgen($array[$arraykeys[$i]]['subitems'][$ii], $tagtext, $text) . '</li>';
                    } else {
                      $subtext = '<li>' . osf_item_textgen($array[$arraykeys[$i]]['subitems'][$ii], $tagtext, $text, 'list style') . '</li>';
                    }
                    $returnstring .= $substart . $subtext . $subend;
                  }
                }
              }
            }
            $returnstring .= '</ul></div>';
          }
        }
      }
    }
  }

  $returnstring .= '</div>' . "\n";
  $cleanupsearch = array(
    $delimiter . '</div>',
    ',</div>',
    $delimiter . ')',
    $delimiter . '(',
    'osf_"',
    'osf_ ',
    '<li></li>',
    '<div class="osf_chapterbox">  <h2></h2><span class="osf_chaptertime" data-time=""></span><ul class="osf_items"> 
</ul></div>'
  );

  $cleanupreplace = array(
    $lastdelimiter . '</div>',
    '</div>',
    ') ',
    ' (',
    ' ',
    ' ',
    '',
    ''
  );

  $returnstring = str_replace($cleanupsearch, $cleanupreplace, $returnstring);

  return $returnstring;
}

function osf_export_osf($array, $full = false, $template = '', $filtertags = array(0 => 'spoiler')) {
  global $shownotes_options;
  $returnstring  = '';
  $filterpattern = array(
    '(\s(#)(\S*))',
    '(\<((http(|s)://[\S#?-]{0,128})>))',
    '(\s+((http(|s)://[\S#?-]{0,128})\s))',
    '(^ *[\-\–\—]*)'
  );
  $arraykeys   = array_keys($array);
  for ($i = 0; $i <= count($array); $i++) {
    if (isset($array[$arraykeys[0]])) {
      if (isset($arraykeys[$i])) {
        if (isset($array[$arraykeys[$i]])) {
          if (((@$array[$arraykeys[$i]]['chapter']) || (($full != false) && (@$array[$arraykeys[$i]]['time'] != ''))) || ($i == 0)) {
            $text = trim(preg_replace($filterpattern, '', @$array[$arraykeys[$i]]['orig']));
            if($text != '') {
              //$returnstring .= "\n";
              if (strpos(@$array[$arraykeys[$i]]['time'], '.')) {
                $time = explode('.', $array[$arraykeys[$i]]['time']);
                $time = $time[0];
                $returnstring .= $time.' ';
              } else {
                $time = @$array[$arraykeys[$i]]['time'];
                $returnstring .= $time.' ';
              }
              $returnstring .= $text;
              if (isset($array[$arraykeys[$i]]['urls'][0])) {
                $returnstring .= ' <' . $array[$arraykeys[$i]]['urls'][0] . '> ';
              }
              if(count(@$array[$arraykeys[$i]]['tags']) !== 0) {
                $returnstring .= ' #'.implode(' #', @$array[$arraykeys[$i]]['tags']);
              }
              $returnstring .= "\n";
            }
            if (isset($array[$arraykeys[$i]]['subitems'])) {
              for ($ii = 0; $ii <= count($array[$arraykeys[$i]]['subitems'], COUNT_RECURSIVE); $ii++) {
                if (isset($array[$arraykeys[$i]]['subitems'][$ii])) {
                  $text = trim(preg_replace($filterpattern, '', $array[$arraykeys[$i]]['subitems'][$ii]['orig']));
                  if($text != '') {
                    if (strpos(@$array[$arraykeys[$i]]['subitems'][$ii]['time'], '.')) {
                      $time = explode('.', @$array[$arraykeys[$i]]['subitems'][$ii]['time']);
                      $time = $time[0];
                      $returnstring .= $time.' ';
                    } else {
                      $time = @$array[$arraykeys[$i]]['subitems'][$ii]['time'];
                      $returnstring .= $time.' ';
                    }
                    if(@$array[$arraykeys[$i]]['subitems'][$ii]['rank'] != 0) {
                      $returnstring .= str_repeat('-', $array[$arraykeys[$i]]['subitems'][$ii]['rank']).' ';
                    }
                    $returnstring .= $text;
                    if (isset($array[$arraykeys[$i]]['subitems'][$ii]['urls'][0])) {
                      $returnstring .= ' <' . $array[$arraykeys[$i]]['subitems'][$ii]['urls'][0] . '> ';
                    }
                    if(count(@$array[$arraykeys[$i]]['subitems'][$ii]['tags']) !== 0) {
                      $returnstring .= ' #'.implode(' #', @$array[$arraykeys[$i]]['subitems'][$ii]['tags']);
                    }
                    $returnstring .= "\n";
                  }
                }
              }
            }
          }
        }
      }
    }
  }
  $returnstring = preg_replace('/[ \t]{2,}/', ' ', $returnstring);
  return $returnstring;
}

function osf_export_chapterlist($array) {
  $returnstring = '';

  // Ensure $array is an array before proceeding
  if (!is_array($array)) {
      return $returnstring; // Return an empty string if $array is not an array
  }

  foreach ($array as $item) {
      if (isset($item['chapter']) && $item['chapter']) {
          $filterpattern = array(
              '((#)(\S*))',
              '(\<((http(|s)://\S{0,128})>))',
              '(\s+((http(|s)://\S{0,128})\s))'
          );
          $text = preg_replace($filterpattern, '', $item['orig']);
          if (strpos($item['time'], '.')) {
              $returnstring .= $item['time'] . ' ' . $text . "\n";
          } else {
              $returnstring .= $item['time'] . '.000 ' . $text . "\n";
          }
      }
  }

  $returnstring = preg_replace('(\s+\n)', "\n", $returnstring);
  return $returnstring;
}

function osf_export_psc($array) {
  $returnstring = '<!-- specify chapter information -->' . "\n" . '<sc:chapters version="1.0">' . "\n";
  foreach ($array as $item) {
    if ($item['chapter']) {
      $filterpattern = array(
        '((#)(\S*))',
        '(\<((http(|s)://[\S#?-]{0,1024})>))',
        '(\s+((http(|s)://[\S#?-]{0,1024})\s))'
      );
      $text          = trim(preg_replace($filterpattern, '', $item['text']));
      if (strpos($item['time'], '.')) {
        $time = $item['time'];
      } else {
        $time = $item['time'] . '.000 ';
      }
      $returnstring .= '<sc:chapter start="' . $time . '" title="' . $text . '"';
      if (isset($item['urls'][0])) {
        $returnstring .= ' href="' . $item['urls'][0] . '"';
      }
      $returnstring .= ' />' . "\n";
    }
  }
  $returnstring .= '</sc:chapters>' . "\n";
  $returnstring = preg_replace('(\s+")', '"', $returnstring);
  return $returnstring;
}

function osf_glossarysort($a, $b) {
  $ax = str_split(strtolower(trim($a['text'])));
  $bx = str_split(strtolower(trim($b['text'])));

  if (count($ax) < count($bx)) {
    for ($i = 0; $i <= count($bx); $i++) {
      if (ord($ax[$i]) != ord($bx[$i])) {
        return (ord($ax[$i]) < ord($bx[$i])) ? -1 : 1;
      }
    }
  } else {
    for ($i = 0; $i <= count($ax); $i++) {
      if (isset($ax[$i], $bx[$i])) {
        if (ord($ax[$i]) != ord($bx[$i])) {
          return (ord($ax[$i]) < ord($bx[$i])) ? -1 : 1;
        }
      }
    }
  }
  return 0;
}

//HTML export as glossary
function osf_export_glossary($array, $showtags = array(0 => '')) {
  $linksbytag = array();

  $filterpattern = array(
    '(\s(#)(\S*))',
    '(\<((http(|s)://[\S#?-]{0,128})>))',
    '(\s+((http(|s)://[\S#?-]{0,128})\s))',
    '(^ *[\-\–\—]*)'
  );
  $arraykeys   = array_keys($array);
  if (!isset($full)) {
    $full = false;
  }
  for ($i = 0; $i <= count($array); $i++) {
    if (((@$array[$arraykeys[$i]]['chapter']) || (($full != false) && (@$array[$arraykeys[$i]]['time'] != ''))) || ($i == 0)) {
      if (isset($array[$arraykeys[$i]]['subitems'])) {
        for ($ii = 0; $ii <= count($array[$arraykeys[$i]]['subitems']); $ii++) {
          if (isset($array[$arraykeys[$i]]['subitems'][$ii]['urls'][0], $array[$arraykeys[$i]]['subitems'][$ii]['orig'])) {
            if (($array[$arraykeys[$i]]['subitems'][$ii]['urls'][0] != '') && ($array[$arraykeys[$i]]['subitems'][$ii]['orig'] != '')) {
              if (isset($array[$arraykeys[$i]]['subitems'][$ii]['tags'])) {
                if (is_array($array[$arraykeys[$i]]['subitems'][$ii]['tags'])) {
                  foreach ($array[$arraykeys[$i]]['subitems'][$ii]['tags'] as $tag) {
                    if (($showtags[0] == '') || (array_search($tag, $showtags) !== false)) {
                      $linksbytag[$tag][$ii]['url']  = $array[$arraykeys[$i]]['subitems'][$ii]['urls'][0];
                      $linksbytag[$tag][$ii]['text'] = $array[$arraykeys[$i]]['subitems'][$ii]['text'];
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  }

  $return = '';

  foreach ($linksbytag as $tagname => $content) {
    $return .= '<h2>' . $tagname . '</h2>' . "\n";
    $return .= '<ol>' . "\n";
    usort($content, "osf_glossarysort");
    foreach ($content as $item) {
      $return .= '<li><a target="_blank" href="' . $item['url'] . '">' . $item['text'] . '</a></li>' . "\n";
    }
    $return .= '</ol>' . "\n";
  }

  return $return;
}

function shownotes_markdown($string) {
  $rules['sm'] = array(
    '/\n(#+)(.*)/e' => 'md_header(\'\\1\', \'\\2\')',                        // headers
    '/\[([^\[]+)\]\(([^\)]+)\)/' => '<a target="_blank" href=\'\2\'>\1</a>', // links
    '/(\*\*\*|___)(.*?)\1/' => '<em><strong>\2</strong></em>',               // bold emphasis
    '/(\*\*|__)(.*?)\1/' => '<strong>\2</strong>',                           // bold
    '/(\*|_)([\w| ]+)\1/' => '<em>\2</em>',                                  // emphasis
    '/\~\~(.*?)\~\~/' => '<del>\1</del>',                                    // del
    '/\:\"(.*?)\"\:/' => '<q>\1</q>',                                        // quote
    '/\n([*]+)\s([[:print:]]*)/e' => 'md_ulist(\'\\1\', \'\\2\')',           // unorderd lists
    '/\n[0-9]+\.(.*)/e' => 'md_olist(\'\\1\')',                              // orderd lists
    '/\n&gt;(.*)/e' => 'md_blockquote(\'\\1\')',                             // blockquotes
    '/\n([^\n]+)\n/e' => 'md_paragraph(\'\\1\')',                            // add paragraphs
    '/<\/ul>(\s*)<ul>/' => '',                                               // fix extra ul
    '/(<\/li><\/ul><\/li><li><ul><li>)/' => '</li><li>',                     // fix extra ul li
    '/(<\/ul><\/li><li><ul>)/' => '',                                        // fix extra ul li
    '/<\/ol><ol>/' => '',                                                    // fix extra ol
    '/<\/blockquote><blockquote>/' => "\n"                                   // fix extra blockquote
  );

  $rules['html'] = array(
    '(\s+((http(|s)://\S{0,64})\s))' => ' <a target="_blank" href="\2">\2</a> ',                                 // url
    '(\s+(([a-zA-Z0-9.,+_-]{1,63}[@][a-zA-Z0-9.,-]{0,254})))' => ' <a target="_blank" href="mailto:\2">\2</a> ', // mail
    '(\s+((\+)[0-9]{5,63}))' => ' <a target="_blank" href="tel:\1">call \1</a>'                                  // phone
  );

  $rules['tweet'] = array(
    '((@)(\S*))' => ' <a target="_blank" href=\'https://twitter.com/\2\'>\1\2</a> ',                             // user
    '((#)(\S*))' => ' <a target="_blank" href=\'https://twitter.com/#!/search/?src=hash&amp;q=%23\2\'>\1\2</a> ' // hashtag
  );

  $string = "\n" . $string . "\n";

  foreach ($rules as $rule) {
    foreach ($rule as $regex => $replace) {
      $string = preg_replace($regex, $replace, $string);
    }
  }

  return trim($string);
}

function md_header($chars, $header) {
  $level = strlen($chars);
  return sprintf('<h%d>%s</h%d>', $level, trim($header), $level);
}

function md_ulist($count, $string) {
  $return = trim($string);
  $count  = strlen($count);
  $i    = 0;
  while ($i != $count) {
    $return = '<ul><li>' . $return . '</li></ul>';
    ++$i;
  }

  return $return;
}

function md_olist($item) {
  return sprintf("\n<ol>\n\t<li>%s</li>\n</ol>", trim($item));
}

function md_blockquote($item) {
  return sprintf("\n<blockquote>%s</blockquote>", trim($item));
}

function md_paragraph($line) {
  $trimmed = trim($line);
  if (strpos($trimmed, '<') === 0) {
    return $line;
  }

  return sprintf("\n<p>%s</p>\n", $trimmed);
}

// Add initialization of global variables
global $osf_starttime;
if (!isset($osf_starttime)) {
    $osf_starttime = 0;
}

global $shownotes_options;
if (!isset($shownotes_options)) {
    $shownotes_options = array();
}

?>
