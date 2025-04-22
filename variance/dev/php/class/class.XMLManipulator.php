<?php

class XMLManipulator
{
    const MARKER_SOURCE = 'a';
    const MARKER_TARGET = 'b';
    private $xml_file = '';

    private $xml = null;

    private $sourceSize;

    private $targetSize;

    private $markerSource;

    private $markerTarget;

    private $informations;

    /**
     *
     * @param string $fileName
     * @param string $markerSource A string to make unique the IDs for the
     * source text. Defaults to <code>a</code>
     * @param string $markerTarget A string to make unique the IDs for the
     * target text. Defaults to <code>b</code>
     */
    public function __construct($fileName = null, $markerSource = self::MARKER_SOURCE, $markerTarget = self::MARKER_TARGET)
    {
        if ($fileName != null) {
            $this->setFileName($fileName);
        }
        $this->markerSource = $markerSource;
        $this->markerTarget = $markerTarget;
    }

    public function setFileName($fileName)
    {
        $this->xml_file = $fileName;
        $this->xml = simplexml_load_file($this->xml_file);
    }

    public function getComparisons()
    {
        $this->sourceSize = $this->getSourceSize();
        $informations = array();
        foreach ($this->xml->informations as $information) {
            $informations[] = $information;
        }
        return $informations;
    }

    public function getXMLFileName($xml_file)
    {
        $xml_file_name = explode('/', $xml_file);
        return end($xml_file_name);
    }

    function openXMLFile($xml_file, $mode = "READ", $input = "")
    {
        if ($mode == "READ") {

            if (file_exists($xml_file)) {

                $handle = fopen($xml_file, "r");
                $output = fread($handle, filesize($xml_file));
                return $output;
            } else {

                return false;
            }
        } elseif ($mode == "WRITE") {

            $handle = fopen($xml_file, "w");

            if (! fwrite($handle, $input)) {

                return false;
            } else {

                return true;
            }
        } elseif ($mode == "READ/WRITE") {

            if (file_exists($xml_file) && isset($input)) {

                $handle = fopen($xml_file, "r+");
                $read = fread($handle, filesize($xml_file));
                $data = $read . $input;

                if (! fwrite($handle, $data)) {

                    return false;
                } else {

                    return true;
                }
            } else {

                return false;
            }
        } else {
            return false;
        }

        fclose($handle);
    }

    public function getInserts()
    {
        $transformations_array = array();
        $i = -1;
        foreach ($this->xml->informations->transformations->insertions->ins as $k) {
            if ((int)$k['f'] - (int)$k['d'] <= 0) {
                continue;
            }
            $transformations_array[] = array(
                'start' => (int)$k['d'],
                'end' => (int)$k['f'],
                'length' => (int)$k['f'] - (int)$k['d'],
                'type' => TextManipulator::MODIFY_INSERTED,
                'id' => sprintf("%05d", ++$i),
                'marker' => array(
                    'source' => ((int)$k['d'] < $this->sourceSize) ? $this->markerSource : $this->markerTarget,
                    'target' => ((int)$k['d'] >= $this->sourceSize) ? $this->markerSource : $this->markerTarget,
                ),
            );
        }
        return $transformations_array;
    }

    public function getRemovals()
    {
        $transformations_array = array();
        $i = -1;
        foreach ($this->xml->informations->transformations->suppressions->sup as $k) {
            if ((int)$k['f'] - (int)$k['d'] <= 0) {
                continue;
            }
            $transformations_array[] = array(
                'start' => (int)$k['d'],
                'end' => (int)$k['f'],
                'length' => (int)$k['f'] - (int)$k['d'],
                'type' => TextManipulator::MODIFY_REMOVED,
                'id' => sprintf("%05d", ++$i),
                'marker' => array(
                    'source' => ((int)$k['d'] < $this->sourceSize) ? $this->markerSource : $this->markerTarget,
                    'target' => ((int)$k['d'] >= $this->sourceSize) ? $this->markerSource : $this->markerTarget,
                ),
            );
        }
        return $transformations_array;
    }

    public function getReplacements()
    {
        $transformations_array = array();
        $i = -1;
        $offset = 0;
        foreach ($this->xml->informations->transformations->remplacements->remp as $k) {
            if ((int)$k['f'] - (int)$k['d'] <= 0) {
                continue;
            }
            if (!$offset && (int)$k['d'] >= $this->sourceSize) {
                $offset = $i + 1;
            }
            $transformation = array(
                'start' => (int)$k['d'],
                'end' => (int)$k['f'],
                'length' => (int)$k['f'] - (int)$k['d'],
                'type' => TextManipulator::MODIFY_REPLACED,
                'id' => sprintf("%05d", ++$i - $offset),
                'marker' => array(
                    'source' => ((int)$k['d'] < $this->sourceSize) ? $this->markerSource : $this->markerTarget,
                    'target' => ((int)$k['d'] >= $this->sourceSize) ? $this->markerSource : $this->markerTarget,
                ),
            );
            if ($offset) {
                $transformations_array[$i - $offset]['replacement'] = array(
                    'start' => (int)$k['d'],
                    'end' => (int)$k['f'],
                    'length' => (int)$k['f'] - (int)$k['d'],
                );
            }
            $transformations_array[] = $transformation;
        }
        return $transformations_array;
    }

    public function getCommons()
    {
        $transformations_array = array();
        $i = -1;
        $offset = 0;
        foreach ($this->xml->informations->transformations->blocscommuns->bc as $k) {
            if ((int)$k['f'] - (int)$k['d'] <= 0) {
                continue;
            }
            if (!$offset && (int)$k['d'] >= $this->sourceSize) {
                $offset = $i + 1;
            }
            $transformations_array[] = array(
                'start' => (int)$k['d'],
                'end' => (int)$k['f'],
                'length' => (int)$k['f'] - (int)$k['d'],
                'type' => TextManipulator::MODIFY_COMMON,
                'id' => sprintf("%05d", ++$i - $offset),
                'marker' => array(
                    'source' => ((int)$k['d'] < $this->sourceSize) ? $this->markerSource : $this->markerTarget,
                    'target' => ((int)$k['d'] >= $this->sourceSize) ? $this->markerSource : $this->markerTarget,
                ),
            );
        }
        return $transformations_array;
    }

    public function getMoved()
    {
        $transformations_array = array();
        $i = 0;
        foreach ($this->xml->informations->transformations->blocsdeplaces->bd as $k) {
            if ((int)$k['b1f'] - (int)$k['b1d'] <= 0) {
                continue;
            }
            $transformations_array[] = array(
                'start' => (int)$k['b1d'],
                'end' => (int)$k['b1f'],
                'length' => (int)$k['b1f'] - (int)$k['b1d'],
                'type' => TextManipulator::MODIFY_MOVED,
                'id' => sprintf("%05d", $i),
                'marker' => array(
                    'source' => $this->markerSource,
                    'target' => $this->markerTarget,
                ),
            );
            $transformations_array[] = array(
                'start' => (int)$k['b2d'],
                'end' => (int)$k['b2f'],
                'length' => (int)$k['b2f'] - (int)$k['b2d'],
                'type' => TextManipulator::MODIFY_MOVED,
                'id' => sprintf("%05d", $i++),
                'marker' => array(
                    'source' => $this->markerTarget,
                    'target' => $this->markerSource,
                ),
            );
        }
        return $transformations_array;
    }

    public function getTransformations()
    {
        $transformations_array = array_merge(
            $this->getInserts(),
            $this->getRemovals(),
            $this->getReplacements(),
            $this->getMoved(),
            $this->getCommons()
        );

        usort($transformations_array, function ($first, $second) {
            $rel = $first['start'] - $second['start'];
            if ($rel == 0) {
                $rel = $first['id'] - $second['id'];
            }
            return $rel;
        }); // re-order by start byte and ID

        $temp = end($transformations_array);
        reset($transformations_array);
        $this->targetSize = $temp['end'] - $this->getSourceSize();
        return $transformations_array;
    }

    public function getTargetOffset()
    {
        return $this->getSourceSize();
    }

    public function getSourceSize()
    {
        if ($this->sourceSize === null) {
            $this->sourceSize = (int)($this->xml->informations->transformations->lgsource->attributes()['lg']);
        }
        return $this->sourceSize;
    }

    public function getTargetSize()
    {
        return $this->targetSize;
    }

    public function getSourceOriginalName()
    {
        return $this->xml->informations->attributes()['fsource'];
    }

    public function getTargetOriginalName()
    {
        return $this->xml->informations->attributes()['fcible'];
    }

    public function getSourceVersion()
    {
        return $this->xml->informations->attributes()['vsource'];
    }

    public function getTargetVersion()
    {
        return $this->xml->informations->attributes()['vcible'];
    }
}

/**
 * Handles one version of the comparison set
 *
 *
 */
class TextManipulator
{
    const MODIFY_REMOVED = 's';

    const MODIFY_INSERTED = 'i';

    const MODIFY_REPLACED = 'r';

    const MODIFY_MOVED = 'd';

    const MODIFY_COMMON = 'c';

    /**
     * The offset to substract from transformation start
     *
     * The transformation file takes a single file, made of the union of source
     * and target, as reference. As we work on separate files, the target is
     * like starting at this offset.
     * @var int
     */
    private $offset = 0;

    private $_source = null;

    private $_modified = null;

    private $parent = null;

    private $previousSibling = null;

	/**
	 * @var array an array to be used with str_replace
	 * keys = ids to replace in r.xhtml
	 * values = text to insert
	 */
	private $replacements;

    /**
     *
     * @param string $sourceFileName
     * @param string $modifiedFileName
     */
    public function __construct($sourceFileName = null, $modifiedFileName = null)
    {
        if ($sourceFileName != null) {
            $this->setSourceFileName($sourceFileName);
        }
        if ($modifiedFileName != null) {
            $this->setModifiedFileName($modifiedFileName);
        }
    }

    public function setSourceFileName($fileName)
    {
        $this->_source = new SplFileObject($fileName, 'r');
        return $this;
    }

    public function setModifiedFileName($fileName, $mode = 'a+')
    {
        $this->_modified = new SplFileObject($fileName, $mode);
        return $this;
    }

    public function getFileName($withPath = false)
    {
        if (!$withPath) {
            $file_name = $this->_source->getFilename();
        } else {
            $file_name = $this->_source->getRealPath();
        }
        return $file_name;
    }

    public function setFile($file)
    {
        $this->_source = $file;
        return $this;
    }

    public function getFile()
    {
        return $this->_source;
    }

    function readFile($mode = 'as_string')
    {
        if (!is_readable($this->getFile())) {
            return null;
        }
        switch ($mode) {
            case 'as_array':
                return file($this->getFile());
            case 'as_file':
                return fopen($this->getFile(), "r");
            default:
            case 'as_string':
                return file_get_contents($this->_source->getRealPath());
        }
    }

    public function countCharsAndWords($txt_file_as_string)
    {
        $num_of_chars = strlen($txt_file_as_string);
        $num_of_words = str_word_count($txt_file_as_string);

        $charsAndWordsArray = array(
            'chars' => $num_of_chars,
            'words' => $num_of_words
        );
        return $charsAndWordsArray;
    }

    public function countLines($txt_file_as_array)
    {
        $num_of_lines = sizeof($txt_file_as_array);
        return $num_of_lines;
    }

    public function getFileInfoArray($file)
    {
        $type = filetype($file);
        $size = filesize($file);

        $fileInfoArray = array(
            'type' => $type,
            'size' => $size
        );
        return $fileInfoArray;
    }

    public function getLine($file, $line_number = 'last')
    {
        if ($line_number == 'last') {
            return end($file);
        } else {
            return $file[$line_number];
        }
    }

    /**
     * @deprecated Use directly number_format instead
     * @param unknown $number
     * @param string $precision
     * @param string $decimal_marker
     * @param string $thousand_marker
     * @return string
     */
    function formatNumber($number, $precision = '0', $decimal_marker = '.', $thousand_marker = '\'')
    {
        return number_format($number, $precision, $decimal_marker, $thousand_marker);
    }

    /**
     * @deprecated
     * @param unknown $file
     * @param string $charset
     * @return mixed
     */
    function formatTxt($file, $charset = 'utf-8')
    {
        $str = htmlentities($file, ENT_NOQUOTES, $charset);

        $str = preg_replace('#\&([A-Za-z])(?:acute|cedil|circ|grave|ring|tilde|uml)\;#', '\1', $str);
        $str = preg_replace('#\&([A-Za-z]{2})(?:lig)\;#', '\1', $str); // pour les ligatures e.g. '&oelig;'
        $str = preg_replace('#\&[^;]+\;#', '', $str); // supprime les autres caractères

        return $str;
    }



	public function mergeReplacements()
	{
		file_put_contents($this->_modified->getPath() . '/r' . '.xhtml', str_replace(array_keys($this->replacements), array_values($this->replacements), file_get_contents($this->_modified->getPath() . '/r'. '.xhtml')));
	}

    /**
     *
     * @param array $transformation
     * @return integer 0 if the transformation wasn't applied, the number of
     * characters that were written else
     */
    public function modify(array $transformation)
    {
        if ($transformation['start'] - $this->offset >= $this->_source->getSize() - 1
            || $transformation['start'] - $this->offset < 0
        ) {
            return 0;
        }
        $text = '';
        if (!$this->previousSibling) {
            // First modification: just output the first tag
            $text .= $this->getOpenPattern($transformation);
            $this->previousSibling = $transformation;
            return $this->_modified->fwrite($text);
        }

        if ($this->parent && $this->parent['end'] <= $transformation['start']) {
            // We have a change of sibling at parent level, so we "step up" by
            // putting content of last children, which is previousSibling — as
            // its parent is over, we can safely retrieve and close it.
            $length = $this->previousSibling['end'] - $this->offset - $this->_source->ftell();
            if ($length > 0) {
                $text .= $this->_source->fread($length);
            }
            $text .= $this->getClosePattern($this->previousSibling);
            // Now children is closed, we set the parent as the previous sibling to handle it mainstream
            $this->previousSibling = $this->parent;
            $this->parent = null;
        }

        if ($this->previousSibling['end'] <= $transformation['start']) {
            // We have a new sibling, so we get remaining of previous one…
            $length = $this->previousSibling['end'] - $this->offset - $this->_source->ftell();
            if ($length > 0) {
                $text .= $this->_source->fread($length);
            }
            // … and close it…
            $text .= $this->getClosePattern($this->previousSibling);
            // … and open for next element
            $text .= $this->getOpenPattern($transformation);
            $this->previousSibling = $transformation;
        }

        if (($transformation['marker']['source'] == 'a' && $transformation['type'] != static::MODIFY_COMMON)
            || ($transformation['type'] == static::MODIFY_INSERTED)
        ) {
            $modified = trim($this->read($transformation['length'], $transformation['start'] - $this->offset));

            if (trim($modified)) {
                $pattern = '<li><a href="#%5$s%1$s_%2$s" id="l%4$s%1$s_%2$s" class="sync';
                switch ($transformation['type']) {
	                case TextManipulator::MODIFY_REPLACED:
		                if(strlen($modified) > 100) {
			                $modified = substr($modified, 0, 100).'…';
		                }
		                $pattern .= ' sync-twice">%3$s &#X2192; l%4$s%1$s_%2$s_REPLACE';
						break;
	                case TextManipulator::MODIFY_COMMON:
                    case TextManipulator::MODIFY_MOVED:
                        $pattern .= ' sync-twice">%3$s';
                        break;
                    case TextManipulator::MODIFY_INSERTED:
                    case TextManipulator::MODIFY_REMOVED:
                    default:
                        $pattern .= '">%3$s';
                        break;
                }
                $pattern .= '</a></li>' . "\n";
                file_put_contents(
                    $this->_modified->getPath() . '/' . $transformation['type'] . '.xhtml',

                    sprintf($pattern,
                        $transformation['type'],
                        $transformation['id'],
                        preg_replace('`\R+`', ' ', $modified),
                        $transformation['marker']['target'],
                        $transformation['marker']['source']
                    ),
                    FILE_APPEND
                );
            }
        }

        if ($transformation['type'] == static::MODIFY_REPLACED && $transformation['marker']['source'] == 'b') {
	        $modified = trim($this->read($transformation['length'], $transformation['start'] - $this->offset));

	        if(strlen($modified) > 100) {
	        	$modified = substr($modified, 0, 100).'&#8230;';
	        }

	        $transformationId = 'l%4$s%1$s_%2$s_REPLACE';

        	$transformationId = sprintf(
        		$transformationId,
		        $transformation['type'],
		        $transformation['id'],
		        '',
		        $transformation['marker']['source']
	        );

        	$this->replacements[$transformationId] = $modified;
        }

        $result = $this->_modified->fwrite($text);
        $this->_modified->fflush();
        return $result;
    }

    public function endModify()
    {
        $text = '';
        if ($this->parent) {
            // We have a change of sibling at parent level, so we "step up" by
            // putting content of last children, which is previousSibling — as
            // its parent is over, we can safely retrieve and close it.
            $length = $this->previousSibling['end'] - $this->offset - $this->_source->ftell();
            if ($length > 0) {
                $text .= $this->_source->fread($length);
            }
            $text .= $this->getClosePattern($this->previousSibling);
            // Now children is closed, we set the parent as the previous sibling to handle it mainstream
            $this->previousSibling = $this->parent;
            $this->parent = null;
        }

        $length = $this->previousSibling['end'] - $this->offset - $this->_source->ftell();
        if ($length > 0) {
            $text .= $this->_source->fread($length);
        }
        // … and close it…
        $text .= $this->getClosePattern($this->previousSibling);
        $this->_modified->fwrite($text);
        $this->_modified->fflush();
    }

    /**
     * Changes the charset of the file
     * @param string $targetCharset The target charset. Must be one supported by iconv
     * @param string $sourceCharset The source charset. Must be one supported by iconv
     */
    public function setCharset($targetCharset = 'UTF-8', $sourceCharset = 'WINDOWS-1252')
    {
        $content = file_get_contents($this->_modified->getPathname());
        $modified = iconv($sourceCharset, $targetCharset, $content);
        return file_put_contents($this->_modified->getPathname(), $modified);
    }

    /**
     *
     * @param string $newLineTarget
     * @param string $newLineSource
     * @return number
     */
    public function setLineEncoding($newLineTarget = "\n", $newLineSource = "\r\n")
    {
        $content = file_get_contents($this->_source->getPathname());
        $content = str_replace($newLineSource, $newLineTarget, $content);
        return file_put_contents($this->_source->getPathname(), $content);
    }

    public function nl2br()
    {
        $content = file_get_contents($this->_modified->getPathname());
        $modified = nl2br($content);
        return file_put_contents($this->_modified->getPathname(), $modified);
    }

    /**
     *
     * @param string $pageBreak
     * @param string $htmlReplacement
     * @param boolean $after
     * @return number
     */
    public function pageBreakAt($pageBreak, $htmlReplacement, $after = false)
    {
        $content = file_get_contents($this->_modified->getPathname());
        if ($after) {
            $htmlReplacement = '$0 ' . $htmlReplacement;
        } else {
            $htmlReplacement = $htmlReplacement . ' $0';
        }
        $result = preg_replace($pageBreak, $htmlReplacement, $content);
        return file_put_contents($this->_modified->getPathname(), $result);
    }

    public function setOffset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    public function getSize()
    {
        return $this->_source->getSize();
    }

    public function getHandled()
    {
        return $this->_source->ftell();
    }

    public function getNotHandled()
    {
        return $this->_source->getSize() - $this->_source->ftell();
    }

    public function read($length, $start = 0)
    {
        $previousPosition = $this->_source->ftell();
        if (is_numeric($start)) {
            $this->_source->fseek($start);
        } else {
            $this->_source->rewind();
        }
        $text = $this->_source->fread($length);
        $this->_source->fseek($previousPosition);
        return $text;
    }

    private function getOpenPattern($transformation)
    {
        switch ($transformation['type']) {
            case static::MODIFY_COMMON:
            case static::MODIFY_MOVED:
            case static::MODIFY_REPLACED:
                $pattern = '<a href="#%4$s%1$s_%2$s" class="span_%1$s sync" id="%3$s%1$s_%2$s" class="sync sync-single">';
                break;
            default:
                $pattern = '<span class="span_%1$s" id="%3$s%1$s_%2$s">';
                break;
        }
        return sprintf(
            $pattern,
            $transformation['type'],
            $transformation['id'],
            $transformation['marker']['source'],
            $transformation['marker']['target']
        );
    }

    private function getClosePattern($transformation)
    {
        switch ($transformation['type']) {
            case static::MODIFY_COMMON:
            case static::MODIFY_MOVED:
            case static::MODIFY_REPLACED:
                $pattern = '</a>';
                break;
            default:
                $pattern = '</span>';
                break;
        }
        return $pattern;
    }
}