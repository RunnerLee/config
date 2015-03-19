<?php
/**
 * Created by PhpStorm.
 * User: janhuang
 * Date: 15/1/30
 * Time: 下午10:16
 * Github: https://www.github.com/janhuang 
 * Coding: https://www.coding.net/janhuang
 * SegmentFault: http://segmentfault.com/u/janhuang
 * Blog: http://segmentfault.com/blog/janhuang
 * Gmail: bboyjanhuang@gmail.com
 */

namespace Dobee\Configuration\Loader;

use Dobee\Configuration\Loader;

class YmlFileLoader extends Loader
{
    private $path;

    private $indent;

    private $result;

    private $delayedPath = array();

    private $_containsGroupAnchor = false;

    private $_containsGroupAlias = false;

    var $LiteralPlaceHolder = '___YAML_Literal_Block___';

    private $SavedGroups = array();

    public function load($resource = null)
    {
        if (!file_exists($resource)) {
            throw new \InvalidArgumentException(sprintf('"%s" not found.', $resource));
        }

        if (!empty($resource) && strpos($resource, "\n") === false && file_exists($resource)) {
            $resource = file_get_contents($resource);
        }

        $resource = explode("\n",$resource);

        foreach ($resource as $key => $value) {
            $resource[$key] = rtrim ($value, "\r");
        }

        $this->setParameters($this->parse($resource));

        return $this;
    }

    private function isComment ($line) {
        if (!$line) {
            return false;
        }
        if ($line[0] == '#') {
            return true;
        }
        if (trim($line, " \r\n\t") == '---') {
            return true;
        }

        return false;
    }

    function isEmpty ($line) {
        return (trim ($line) === '');
    }

    function addLiteralLine ($literalBlock, $line, $literalBlockStyle) {
        $line = $this->stripIndent($line);
        $line = rtrim ($line, "\r\n\t ") . "\n";
        if ($literalBlockStyle == '|') {
            return $literalBlock . $line;
        }
        if (strlen($line) == 0)
            return rtrim($literalBlock, ' ') . "\n";
        if ($line == "\n" && $literalBlockStyle == '>') {
            return rtrim ($literalBlock, " \t") . "\n";
        }
        if ($line != "\n")
            $line = trim ($line, "\r\n ") . " ";
        return $literalBlock . $line;
    }

    function getParentPathByIndent ($indent) {
        if ($indent == 0) return array();
        $linePath = $this->path;
        do {
            end($linePath); $lastIndentInParentPath = key($linePath);
            if ($indent <= $lastIndentInParentPath) array_pop ($linePath);
        } while ($indent <= $lastIndentInParentPath);
        return $linePath;
    }

    function stripIndent ($line, $indent = -1) {
        if ($indent == -1) $indent = strlen($line) - strlen(ltrim($line));
        return substr ($line, $indent);
    }

    function startsLiteralBlock ($line) {
        $lastChar = substr (trim($line), -1);
        if ($lastChar != '>' && $lastChar != '|') return false;
        if ($lastChar == '|') return $lastChar;
        // HTML tags should not be counted as literal blocks.
        if (preg_match ('#<.*?>$#', $line)) return false;
        return $lastChar;
    }

    function literalBlockContinues ($line, $lineIndent) {
        if (!trim($line)) return true;
        if (strlen($line) - strlen(ltrim($line)) > $lineIndent) return true;
        return false;
    }

    function greedilyNeedNextLine($line) {
        $line = trim ($line);
        if (!strlen($line)) return false;
        if (substr ($line, -1, 1) == ']') return false;
        if ($line[0] == '[') return true;
        if (preg_match ('#^[^:]+?:\s*\[#', $line)) return true;
        return false;
    }

    function _parseLine($line) {
        if (!$line) return array();
        $line = trim($line);

        if (!$line) return array();
        $array = array();

        $group = $this->nodeContainsGroup($line);
        if ($group) {
            $this->addGroup($line, $group);
            $line = $this->stripGroup ($line, $group);
        }

        if ($this->startsMappedSequence($line))
            return $this->returnMappedSequence($line);

        if ($this->startsMappedValue($line))
            return $this->returnMappedValue($line);

        if ($this->isArrayElement($line))
            return $this->returnArrayElement($line);

        if ($this->isPlainArray($line))
            return $this->returnPlainArray($line);


        return $this->returnKeyValuePair($line);

    }

    function returnPlainArray ($line) {
        return $this->_toType($line);
    }

    function isArrayElement ($line) {
        if (!$line) return false;
        if ($line[0] != '-') return false;
        if (strlen ($line) > 3)
            if (substr($line,0,3) == '---') return false;

        return true;
    }

    function startsMappedSequence ($line) {
        return ($line[0] == '-' && substr ($line, -1, 1) == ':');
    }

    function returnMappedSequence ($line) {
        $array = array();
        $key         = $this->unquote(trim(substr($line,1,-1)));
        $array[$key] = array();
        $this->delayedPath = array(strpos ($line, $key) + $this->indent => $key);
        return array($array);
    }

    function unquote ($value) {
        if (!$value) return $value;
        if (!is_string($value)) return $value;
        if ($value[0] == '\'') return trim ($value, '\'');
        if ($value[0] == '"') return trim ($value, '"');
        return $value;
    }

    function returnMappedValue ($line) {
        $array = array();
        $key         = $this->unquote (trim(substr($line,0,-1)));
        $array[$key] = '';
        return $array;
    }

    function startsMappedValue ($line) {
        return (substr ($line, -1, 1) == ':');
    }

    function isPlainArray ($line) {
        return ($line[0] == '[' && substr ($line, -1, 1) == ']');
    }

    function returnKeyValuePair ($line) {
        $array = array();
        $key = '';
        if (strpos ($line, ':')) {
            // It's a key/value pair most likely
            // If the key is in double quotes pull it out
            if (($line[0] == '"' || $line[0] == "'") && preg_match('/^(["\'](.*)["\'](\s)*:)/',$line,$matches)) {
                $value = trim(str_replace($matches[1],'',$line));
                $key   = $matches[2];
            } else {
                // Do some guesswork as to the key and the value
                $explode = explode(':',$line);
                $key     = trim($explode[0]);
                array_shift($explode);
                $value   = trim(implode(':',$explode));
            }
            // Set the type of the value.  Int, string, etc
            $value = $this->_toType($value);
            if ($key === '0') $key = '__!YAMLZero';
            $array[$key] = $value;
        } else {
            $array = array ($line);
        }
        return $array;

    }

    function _toType($value) {
        if ($value === '') return null;
        $first_character = $value[0];
        $last_character = substr($value, -1, 1);

        $is_quoted = false;
        do {
            if (!$value) break;
            if ($first_character != '"' && $first_character != "'") break;
            if ($last_character != '"' && $last_character != "'") break;
            $is_quoted = true;
        } while (0);

        if ($is_quoted)
            return strtr(substr ($value, 1, -1), array ('\\"' => '"', '\'\'' => '\'', '\\\'' => '\''));

        if (strpos($value, ' #') !== false)
            $value = preg_replace('/\s+#(.+)$/','',$value);

        if ($first_character == '[' && $last_character == ']') {
            // Take out strings sequences and mappings
            $innerValue = trim(substr ($value, 1, -1));
            if ($innerValue === '') return array();
            $explode = $this->_inlineEscape($innerValue);
            // Propagate value array
            $value  = array();
            foreach ($explode as $v) {
                $value[] = $this->_toType($v);
            }
            return $value;
        }

        if (strpos($value,': ')!==false && $first_character != '{') {
            $array = explode(': ',$value);
            $key   = trim($array[0]);
            array_shift($array);
            $value = trim(implode(': ',$array));
            $value = $this->_toType($value);
            return array($key => $value);
        }

        if ($first_character == '{' && $last_character == '}') {
            $innerValue = trim(substr ($value, 1, -1));
            if ($innerValue === '') return array();
            // Inline Mapping
            // Take out strings sequences and mappings
            $explode = $this->_inlineEscape($innerValue);
            // Propagate value array
            $array = array();
            foreach ($explode as $v) {
                $SubArr = $this->_toType($v);
                if (empty($SubArr)) continue;
                if (is_array ($SubArr)) {
                    $array[key($SubArr)] = $SubArr[key($SubArr)]; continue;
                }
                $array[] = $SubArr;
            }
            return $array;
        }

        if ($value == 'null' || $value == 'NULL' || $value == 'Null' || $value == '' || $value == '~') {
            return null;
        }

        if (intval($first_character) > 0 && preg_match ('/^[1-9]+[0-9]*$/', $value)) {
            $intvalue = (int)$value;
            if ($intvalue != PHP_INT_MAX)
                $value = $intvalue;
            return $value;
        }

        if (in_array($value,
            array('true', 'on', '+', 'yes', 'y', 'True', 'TRUE', 'On', 'ON', 'YES', 'Yes', 'Y'))) {
            return true;
        }

        if (in_array(strtolower($value),
            array('false', 'off', '-', 'no', 'n'))) {
            return false;
        }

        if (is_numeric($value)) {
            if ($value === '0') return 0;
            if (trim ($value, 0) === $value)
                $value = (float)$value;
            return $value;
        }

        return $value;
    }

    function returnArrayElement ($line) {
        if (strlen($line) <= 1) return array(array()); // Weird %)
        $array = array();
        $value   = trim(substr($line,1));
        $value   = $this->_toType($value);
        $array[] = $value;
        return $array;
    }

    function _inlineEscape($inline) {
        // There's gotta be a cleaner way to do this...
        // While pure sequences seem to be nesting just fine,
        // pure mappings and mappings with sequences inside can't go very
        // deep.  This needs to be fixed.

        $seqs = array();
        $maps = array();
        $saved_strings = array();

        // Check for strings
        $regex = '/(?:(")|(?:\'))((?(1)[^"]+|[^\']+))(?(1)"|\')/';
        if (preg_match_all($regex,$inline,$strings)) {
            $saved_strings = $strings[0];
            $inline  = preg_replace($regex,'YAMLString',$inline);
        }
        unset($regex);

        $i = 0;
        do {

            // Check for sequences
            while (preg_match('/\[([^{}\[\]]+)\]/U',$inline,$matchseqs)) {
                $seqs[] = $matchseqs[0];
                $inline = preg_replace('/\[([^{}\[\]]+)\]/U', ('YAMLSeq' . (count($seqs) - 1) . 's'), $inline, 1);
            }

            // Check for mappings
            while (preg_match('/{([^\[\]{}]+)}/U',$inline,$matchmaps)) {
                $maps[] = $matchmaps[0];
                $inline = preg_replace('/{([^\[\]{}]+)}/U', ('YAMLMap' . (count($maps) - 1) . 's'), $inline, 1);
            }

            if ($i++ >= 10) break;

        } while (strpos ($inline, '[') !== false || strpos ($inline, '{') !== false);

        $explode = explode(', ',$inline);
        $stringi = 0; $i = 0;

        while (1) {

            // Re-add the sequences
            if (!empty($seqs)) {
                foreach ($explode as $key => $value) {
                    if (strpos($value,'YAMLSeq') !== false) {
                        foreach ($seqs as $seqk => $seq) {
                            $explode[$key] = str_replace(('YAMLSeq'.$seqk.'s'),$seq,$value);
                            $value = $explode[$key];
                        }
                    }
                }
            }

            // Re-add the mappings
            if (!empty($maps)) {
                foreach ($explode as $key => $value) {
                    if (strpos($value,'YAMLMap') !== false) {
                        foreach ($maps as $mapk => $map) {
                            $explode[$key] = str_replace(('YAMLMap'.$mapk.'s'), $map, $value);
                            $value = $explode[$key];
                        }
                    }
                }
            }


            // Re-add the strings
            if (!empty($saved_strings)) {
                foreach ($explode as $key => $value) {
                    while (strpos($value,'YAMLString') !== false) {
                        $explode[$key] = preg_replace('/YAMLString/',$saved_strings[$stringi],$value, 1);
                        unset($saved_strings[$stringi]);
                        ++$stringi;
                        $value = $explode[$key];
                    }
                }
            }

            $finished = true;
            foreach ($explode as $key => $value) {
                if (strpos($value,'YAMLSeq') !== false) {
                    $finished = false; break;
                }
                if (strpos($value,'YAMLMap') !== false) {
                    $finished = false; break;
                }
                if (strpos($value,'YAMLString') !== false) {
                    $finished = false; break;
                }
            }
            if ($finished) break;

            $i++;
            if ($i > 10)
                break; // Prevent infinite loops.
        }

        return $explode;
    }

    function nodeContainsGroup ($line) {
        $symbolsForReference = 'A-z0-9_\-';
        if (strpos($line, '&') === false && strpos($line, '*') === false) return false; // Please die fast ;-)
        if ($line[0] == '&' && preg_match('/^(&['.$symbolsForReference.']+)/', $line, $matches)) return $matches[1];
        if ($line[0] == '*' && preg_match('/^(\*['.$symbolsForReference.']+)/', $line, $matches)) return $matches[1];
        if (preg_match('/(&['.$symbolsForReference.']+)$/', $line, $matches)) return $matches[1];
        if (preg_match('/(\*['.$symbolsForReference.']+$)/', $line, $matches)) return $matches[1];
        if (preg_match ('#^\s*<<\s*:\s*(\*[^\s]+).*$#', $line, $matches)) return $matches[1];
        return false;

    }

    function addGroup ($line, $group) {
        if ($group[0] == '&') $this->_containsGroupAnchor = substr ($group, 1);
        if ($group[0] == '*') $this->_containsGroupAlias = substr ($group, 1);
        //print_r ($this->path);
    }

    function stripGroup ($line, $group) {
        $line = trim(str_replace($group, '', $line));
        return $line;
    }

    public function parse($resource = null)
    {
        if (empty($resource)) {
            return array();
        }

        $this->path = array();
        $this->result = array();

        $totalLine = count($resource);

        for ($i = 0; $i < $totalLine; $i++) {
            $line = $resource[$i];

            $this->indent = strlen($line) - strlen(ltrim($line));
            $tempPath = $this->getParentPathByIndent($this->indent);
            $line = self::stripIndent($line, $this->indent);
            if (self::isComment($line)) continue;
            if (self::isEmpty($line)) continue;
            $this->path = $tempPath;

            $literalBlockStyle = self::startsLiteralBlock($line);
            if ($literalBlockStyle) {
                $line = rtrim ($line, $literalBlockStyle . " \n");
                $literalBlock = '';
                $line .= ' '.$this->LiteralPlaceHolder;
                $literal_block_indent = strlen($resource[$i+1]) - strlen(ltrim($resource[$i+1]));
                while (++$i < $totalLine && $this->literalBlockContinues($resource[$i], $this->indent)) {
                    $literalBlock = $this->addLiteralLine($literalBlock, $resource[$i], $literalBlockStyle, $literal_block_indent);
                }
                $i--;
            }

            // Strip out comments
            if (strpos ($line, '#')) {
                $line = preg_replace('/\s*#([^"\']+)$/','',$line);
            }

            while (++$i < $totalLine && self::greedilyNeedNextLine($line)) {
                $line = rtrim ($line, " \n\t\r") . ' ' . ltrim ($resource[$i], " \t");
            }
            $i--;

            $lineArray = $this->_parseLine($line);

            if ($literalBlockStyle)
                $lineArray = $this->revertLiteralPlaceHolder ($lineArray, $literalBlock);

            $this->addArray($lineArray, $this->indent);

            foreach ($this->delayedPath as $indent => $delayedPath)
                $this->path[$indent] = $delayedPath;

            $this->delayedPath = array();

        }

        return $this->result;
    }

    function addArrayInline ($array, $indent) {
        $CommonGroupPath = $this->path;
        if (empty ($array)) return false;

        foreach ($array as $k => $_) {
            $this->addArray(array($k => $_), $indent);
            $this->path = $CommonGroupPath;
        }
        return true;
    }

    function addArray ($incoming_data, $incoming_indent) {

        // print_r ($incoming_data);

        if (count ($incoming_data) > 1)
            return $this->addArrayInline ($incoming_data, $incoming_indent);

        $key = key ($incoming_data);
        $value = isset($incoming_data[$key]) ? $incoming_data[$key] : null;
        if ($key === '__!YAMLZero') $key = '0';

        if ($incoming_indent == 0 && !$this->_containsGroupAlias && !$this->_containsGroupAnchor) { // Shortcut for root-level values.
            if ($key || $key === '' || $key === '0') {
                $this->result[$key] = $value;
            } else {
                $this->result[] = $value; end ($this->result); $key = key ($this->result);
            }
            $this->path[$incoming_indent] = $key;
            return;
        }



        $history = array();
        // Unfolding inner array tree.
        $history[] = $_arr = $this->result;
        foreach ($this->path as $k) {
            $history[] = $_arr = $_arr[$k];
        }

        if ($this->_containsGroupAlias) {
            $value = $this->referenceContentsByAlias($this->_containsGroupAlias);
            $this->_containsGroupAlias = false;
        }


        // Adding string or numeric key to the innermost level or $this->arr.
        if (is_string($key) && $key == '<<') {
            if (!is_array ($_arr)) { $_arr = array (); }
            $_arr = array_merge ($_arr, $value);
        } else if ($key || $key === '' || $key === '0') {
            $_arr[$key] = $value;
        } else {
            if (!is_array ($_arr)) { $_arr = array ($value); $key = 0; }
            else { $_arr[] = $value; end ($_arr); $key = key ($_arr); }
        }

        $reverse_path = array_reverse($this->path);
        $reverse_history = array_reverse ($history);
        $reverse_history[0] = $_arr;
        $cnt = count($reverse_history) - 1;
        for ($i = 0; $i < $cnt; $i++) {
            $reverse_history[$i+1][$reverse_path[$i]] = $reverse_history[$i];
        }
        $this->result = $reverse_history[$cnt];

        $this->path[$incoming_indent] = $key;

        if ($this->_containsGroupAnchor) {
            $this->SavedGroups[$this->_containsGroupAnchor] = $this->path;
            if (is_array ($value)) {
                $k = key ($value);
                if (!is_int ($k)) {
                    $this->SavedGroups[$this->_containsGroupAnchor][$incoming_indent + 2] = $k;
                }
            }
            $this->_containsGroupAnchor = false;
        }

    }

    function referenceContentsByAlias ($alias) {
        do {
            if (!isset($this->SavedGroups[$alias])) { echo "Bad group name: $alias."; break; }
            $groupPath = $this->SavedGroups[$alias];
            $value = $this->result;
            foreach ($groupPath as $k) {
                $value = $value[$k];
            }
        } while (false);
        return $value;
    }

    function revertLiteralPlaceHolder ($lineArray, $literalBlock) {
        foreach ($lineArray as $k => $_) {
            if (is_array($_))
                $lineArray[$k] = $this->revertLiteralPlaceHolder ($_, $literalBlock);
            else if (substr($_, -1 * strlen ($this->LiteralPlaceHolder)) == $this->LiteralPlaceHolder)
                $lineArray[$k] = rtrim ($literalBlock, " \r\n");
        }
        return $lineArray;
    }
}