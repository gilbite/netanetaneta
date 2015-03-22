<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class PythonString
{
    protected $str = '';

    public function __construct($str = '')
    {
        $this->str = $str;
    }

    public function __toString()
    {
        return $this->str;
    }
}

class PythonNumeric
{
    protected $numeric;

    public function __construct($numeric) {
        $this->numeric = $numeric;
    }

    public function __toString()
    {
        return $this->numeric;
    }

    public function __toNumeric()
    {
        return $this->numeric;
    }

}

class PythonExecutor
{
    protected $expressions;
    protected $assignees = [];
    protected $compiled = [];

    public function  __construct($expressions)
    {
        $this->expressions = explode("\n", $expressions);
        $this->lang = new ExpressionLanguage();
        $this->lang->register(
            'print',
            function ($str) {
                return 'print($str . PHP_EOL)';
            },
            function ($arguments, $str) {
                print($str . PHP_EOL);
            }
        );

        $this->compile();
    }

    public function execute()    
    {
        $assignees = array_map(
            function ($a) {
                if (is_object($a) && ($a instanceof PythonNumeric)) {
                    return $a->__toNumeric();
                } else {
                    return $a;
                }
            },
            $this->assignees
        );
        foreach($this->compiled as $compiled) {
            $this->lang->evaluate($compiled, $assignees);
        }
    }

    public  function compile()
    {
        if ($this->compiled || $this->assignees) {
            throw new RuntimeException('cannot call twice');
        }

        foreach ($this->expressions as $line) {
            $this->parseLine(trim($line));
        }
    }

    public function parseLine($line)
    {
        if ($parsed = $this->doParse($line)) {
            $this->compiled[] = $parsed;
        }
    }

    public function doParse($line)
    {
        if (preg_match('/(.+)=(.+)/', $line, $match)) {
            $this->assignees[trim($match[1])] = $this->varFactory(trim($match[2]));
            return;
        } elseif (preg_match('/print (.+)/', $line, $match)) {
            return  $this->doParse('print(' . $match[1] . ')');
        } elseif (preg_match('/([a-zA-Z0-9_\-]+) ?\+ ?([a-zA-Z0-9_\-]+)/', $line, $match)) {
            $left  = trim($match[1]);
            $right = trim($match[2]);
            foreach (range(1, 2) as $k) {
                $key = $match[$k];
                if (!array_key_exists($key, $this->assignees) || !($this->assignees[$key] instanceof PythonString)) {
                    return $line;
                }
            }

            return $this->doParse(preg_replace('/([a-zA-Z0-9_\-]+) ?\+ ?([a-zA-Z0-9_\-]+)/', '$1 ~ $2', $line));
        } elseif ($line) {
            return $line;
        }
    }

    protected function varFactory($var)
    {
        if (preg_match('/^"(.*)"$/', $var, $match)) {
            return new PythonString($match[1]);
        } elseif (ctype_digit($var)) {
            return new PythonNumeric($var);
        }
    }
}

$script = <<<EOF
teststring_one = "hoge"
teststring_two = "foo"
teststring_three = "piyo"
int_one = 1
int_two = 2

print teststring_one
print int_one + int_two
print int_two ** 2
print teststring_one + teststring_two
print teststring_one + teststring_two + teststring_three
EOF;

$executor = new PythonExecutor($script);
$executor->execute();
