<?php
array(1=>1)
0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0:0

false:0:'rien'
false?'aa':'':0:'rien'
(false?'aa':''):0:'rien' // 6

false?'aa':('':0:'rien')

trim(0:false:2)

$selection!count
$selection[1]!name
$this->getObject()!name
$this!path

!true
!!false
5 or !6
6 || !6
5 and !6
6 && !6
(!true)
not true
true or false;
'chaîne concaténée à ' . ''
array()
array(false)
(int)("5"/2)
(int)('5'/2)
(int)('5$i'/2)
(int)("5$i"/2)
0+1+2+4+8
0+1+2+4+8+$x
3.14*2*2.0
PHP_OS // constante php pré-définie
PHP_OS_NEW // constante inexistante
'a string'
"a string"
$h
'$h'
"$h"
-$x
$x+$y
trim ("     aaaaa   ")
str_repeat('X',10)
array(array(PHP_OS,1,1+1),$mode)
trim("aa"."bb".substr("cc",1).str_replace("dd","d","ee"))
explode(' ', 'une chaîne avec des espaces')
explode(' ', '')
explode(' ', 'une chaîne')
select(trim(trim("aa"."bb")))
"variable $h dans une chaine"
{"variable {$h[0]} dans une chaine"}
"le label" !== $$name'
$record[ref] !== 0'
$$nomVar
5 * NULL
5 * ''
5 * ' '
call_user_func('my_func', $param1, $param2)
$this->equation
"le $first {$first} label" !== ''
trim($x)
($x==1or$x==2)&&!$y=6
("1"==1 or (1+1)==2) && !(2*5-4)==6
__FILE__ // faut-il gérer ? (retournerait path du template)
self::staticMethod() or $this->canBeCalled(5)
die()
false || die()
exit()
__halt_compiler() // voir s'il y a d'autres fausses fonctions comme celles-là
set_time_limit(0)
ignore_user_abort(true)
sys_getloadavg()
get_browser()
show_source("page.php")
time_sleep_until(mktime(0, 0, 0, 03, 12, 2007))
empty($x)
$x && (($x = 3) == 3)
$x && $x && $x && $x && $x && $x && $x && $x && $x
$x || $x
$x && unset($x)
$selection!count
$x &= 12; // interdit
implode(',', array(1=>'un',2=>'deux',3=>'trois'))
eval('return 12;');
eval('return' . '$' . '$nomVar')
$x=5