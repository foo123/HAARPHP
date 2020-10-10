#!/usr/bin/env php
<?php

/**
*
* CLI script
* Convert OpenCV Haar XML cascade files to PHP format
* for faster loading/execution in HAARPHP
*
* @package HAARPHP
* https://github.com/foo123/HAARPHP
*
* @version: 1.0.0
*
*
**/

if (!class_exists('HaarToPhpConverter'))
{
class HaarToPhpConverter
{
    public static function error($msg)
    {
        trigger_error($msg,  E_USER_WARNING);
        die(1);
    }

    private static function __echo($s = "")
    {
        echo $s . PHP_EOL;
    }

    private static function fileinfo($file)
    {
        $info = pathinfo($file);
        return ! isset($info['filename']) ? '' : $info['filename'];
    }

    /**
     * parseArgs Command Line Interface (CLI) utility function.
     * @author              Patrick Fisher <patrick@pwfisher.com>
     * @see                 https://github.com/pwfisher/CommandLine.php
     */
    private static function parseArgs($argv = null)
    {
        $argv = $argv ? $argv : $_SERVER['argv'];
        array_shift($argv);
        $o = array();
        for ($i = 0, $j = count($argv); $i < $j; $i++)
        {
            $a = $argv[$i];
            if (substr($a, 0, 2) === '--')
            {
                $eq = strpos($a, '=');
                if ($eq !== false)
                {
                    $o[substr($a, 2, $eq - 2)] = substr($a, $eq + 1);
                }
                else
                {
                    $k = substr($a, 2);
                    if ($i + 1 < $j && $argv[$i + 1][0] !== '-')
                    {
                        $o[$k] = $argv[$i + 1];
                        $i++;
                    }
                    elseif (! isset($o[$k]))
                    {
                        $o[$k] = true;
                    }
                }
            }
            elseif (substr($a, 0, 1) === '-')
            {
                if (substr($a, 2, 1) === '=')
                {
                    $o[substr($a, 1, 1)] = substr($a, 3);
                }
                else
                {
                    foreach (str_split(substr($a, 1)) as $k)
                    {
                        if (! isset($o[$k]))
                        {
                            $o[$k] = true;
                        }
                    }
                    if ($i + 1 < $j && $argv[$i + 1][0] !== '-')
                    {
                        $o[$k] = $argv[$i + 1];
                        $i++;
                    }
                }
            }
            else
            {
                $o[] = $a;
            }
        }
        return $o;
    }

    private static function parse($argv = null)
    {
        $defaultArgs = array(
            'h' => false,
            'help' => false,
            'xml' => false
        );
        $args = self::parseArgs($argv);
        $args = array_intersect_key($args, $defaultArgs);
        $args = array_merge($defaultArgs, $args);

        if ( empty($args['xml']) || $args['h'] || $args['help'] )
        {
            // If no xml have been passed or help is set, show the help message and exit
            $p = pathinfo(__FILE__);
            $thisFile = isset($p['extension']) ? ($p['filename'] . '.' . $p['extension']) : $p['filename'];

            self::__echo ("usage: $thisFile [-h] [--xml=FILE]");
            self::__echo ();
            self::__echo ("Transform OpenCV XML HAAR Cascades");
            self::__echo ("to PHP for use with HAARPHP");
            self::__echo ();
            self::__echo ("optional arguments:");
            self::__echo ("  -h, --help      show this help message and exit");
            self::__echo ("  --xml=FILE      OpenCV XML file (REQUIRED)");
            self::__echo ();
            exit(1);
        }
        return $args;
    }

    private static function toArray($element)
    {
        if (! empty($element) && is_object($element))
        {
            $element = (array)$element;
        }
        if (empty($element))
        {
            $element = '';
        }
        if (is_array($element))
        {
            foreach ($element as $k => $v)
            {
                if (empty($v))
                {
                    $element[$k] = '';
                    continue;
                }
                $add = self::toArray($v);
                if (! empty($add))
                {
                    $element[$k] = $add;
                }
                else
                {
                    $element[$k] = '';
                }
            }
        }

        if (empty($element))
        {
            $element = '';
        }
        return $element;
    }

    private static function readXML($file)
    {
        $data = array();
        $info = pathinfo($file);
        $is_zip = $info['extension'] === 'zip';
        if ($is_zip && function_exists('zip_open'))
        {
            $zip = zip_open($file);
            if (is_resource($zip))
            {
                $zip_entry = zip_read($zip);
                if (is_resource($zip_entry) && zip_entry_open($zip, $zip_entry))
                {
                    $data = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                    zip_entry_close($zip_entry);
                }
                else
                    self::error('No zip entry');
            }
            else
            {
                self::error('Unable to open zip file');
            }
        }
        else
        {
            $fh = fopen($file, 'r');
            if ($fh)
            {
                $data = fread($fh, filesize($file));
                fclose($fh);
            }
        }

        if (! empty($data))
        {
            if (! function_exists('simplexml_load_string'))
            {
                self::error('The Simple XML library is missing.');
            }
            $xml = simplexml_load_string($data);
            if (! $xml)
            {
                self::error(sprintf('The XML file (%s) could not be read.', $file));
            }

            $data = self::toArray($xml);
            return $data;

        }
        else
        {
            self::error('Could not read the import file.');
        }
        self::error('Unknown error during import');
    }

    public static function convert($infile, $var_in_php = '')
    {
        $data = self::readXML($infile);

        $racine = reset($data);


        echo("<?php \n");
        echo(empty($var_in_php) ? "return array(" : '$' . $var_in_php . ' = array(');

        $size = explode(' ', trim($racine["size"]));
        $size1 = isset($size[0]) ? $size[0] : 0;
        $size2 = isset($size[1]) ? $size[1] : 0;
        echo("'size1' => " . $size1 . ",'size2' => " . $size2);

        echo(", 'stages' => array(");
        if (isset($racine['stages']['_']))
        {
            $i1 = 0;
            foreach ($racine['stages']['_'] as $stage)
            {
                if (0 === $i1) echo("array(");
                else echo(", array(");

                $thres = (isset($stage["stage_threshold"])) ? $stage["stage_threshold"] : 0;
                echo("'thres' => " . $thres);
                echo(", 'trees' => array(");

                if (isset($stage['trees']['_']))
                {
                    $i2 = 0;
                    foreach($stage['trees']['_'] as $tree)
                    {
                        if (0 === $i2) echo("array(");
                        else echo(", array(");

                        echo("'feats' => array(");

                        $i4 = 0;
                        $feature = (isset($tree['_'])) ? $tree['_'] : null;
                        if ($feature)
                        {
                            if (0 === $i4)  echo("array(");
                            else echo(", array(");

                            $thres2 = (isset($feature["threshold"])) ? $feature["threshold"] : 0;
                            $left_node = "-1";
                            $left_val = "0";
                            $has_left_val = "0";
                            $right_node = "-1";
                            $right_val = "0";
                            $has_right_val = "0";
                            if(isset($feature["left_val"]))
                            {
                                $left_val = $feature["left_val"];
                                $has_left_val = "1";
                            }
                            else
                            {
                                $left_node = $feature["left_node"];
                                $has_left_val = "0";
                            }

                            if(isset($feature["right_val"]))
                            {
                                $right_val = $feature["right_val"];
                                $has_right_val = "1";
                            }
                            else
                            {
                                $right_node = $feature["right_node"];
                                $has_right_val = "0";
                            }
                            echo("'thres' => " . $thres2);
                            echo(", 'has_l' => " . $has_left_val . ", 'l_val' => " . $left_val . ", 'l_node' => " . $left_node);
                            echo(", 'has_r' => " . $has_right_val . ", 'r_val' => " . $right_val . ", 'r_node' => " . $right_node);

                            // incorporate tilted features (Rainer Lienhart et al.)
                            if (isset($feature['feature']['tilted']))
                            {
                                if (!empty($feature['feature']['tilted'])) echo(", 'tilt' => 1");
                                else echo(", 'tilt' => 0");
                            }
                            else
                            {
                                echo(", 'tilt' => 0");
                            }
                            echo(", 'rects' => array(");
                            if (isset($feature['feature']['rects']['_']))
                            {
                                $i3 = 0;
                                foreach($feature['feature']['rects']['_'] as $rect)
                                {
                                    if (0 === $i3) echo("array(");
                                    else echo(", array(");
                                    echo(implode(", ", explode(' ', trim($rect))));
                                    echo(")");
                                    $i3++;
                                }
                            }
                            echo("))");
                            $i4++;
                        }
                        echo("))");
                        $i2++;
                    }
                }
                echo("))");
                $i1++;
            }
        }
        echo("));\n");
    }

    public static function main($argv = null)
    {
        $args = self::parse($argv);
        $infile = realpath($args['xml']);
        $var_in_php = strval(self::fileinfo($infile));
        self::convert($infile, $var_in_php);
    }
}
}

// do the process
error_reporting(E_ALL);
HaarToPhpConverter::main($argv);
