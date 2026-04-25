## Primitive types

bool, int, float, string, null
array — ordered map (int|string keys)
object, callable, iterable
mixed — any type (PHPDoc/type hint)
never — function never returns (throws/exits)
void — no return value

## Type declarations

Scalar: int, float, string, bool
Compound: array, callable, iterable, object
Class/interface name
Union: int|string
Intersection: Countable&Iterator
Nullable: ?int (shorthand for int|null)
Return type void, never, static, self
Strict types: declare(strict_types=1)

## Type juggling / casting

(int), (float), (string), (bool), (array), (object)
intval(), floatval(), strval(), boolval()
settype($var, 'int')
Type coercion rules: "1" == 1 true, "1" === 1 false
Loose comparison (==) vs strict (===)
intdiv() — integer division
round(), floor(), ceil()

## String functions

strlen(), mb_strlen() — byte vs char length
str_contains(), str_starts_with(), str_ends_with() — PHP 8.0+
strpos(), strrpos(), stripos()
substr(), mb_substr()
str_replace(), str_ireplace(), str_repeat()
sprintf(), printf(), number_format(), money_format (deprecated)
trim(), ltrim(), rtrim()
strtolower(), strtoupper(), ucfirst(), ucwords()
explode(), implode() / join()
str_split(), chunk_split()
nl2br(), wordwrap()
htmlspecialchars(), htmlspecialchars_decode(), strip_tags()
addslashes(), stripslashes()
md5(), sha1(), hash(), password_hash(), password_verify()
preg_match(), preg_match_all(), preg_replace(), preg_split()
Heredoc (<<<EOT), Nowdoc (<<<'EOT')
String interpolation: "$var", "{$obj->prop}"

## Array functions

array_map(), array_filter(), array_reduce()
array_walk(), array_walk_recursive()
array_push(), array_pop(), array_shift(), array_unshift()
array_merge(), array_merge_recursive(), array_replace()
array_combine(), array_zip_key (manual with array_combine)
array_diff(), array_diff_key(), array_intersect(), array_intersect_key()
array_unique()
sort(), rsort(), asort(), arsort(), ksort(), krsort(), usort(), uasort(), uksort()
array_slice(), array_splice()
array_chunk()
array_flip()
array_keys(), array_values()
array_key_exists(), isset(), in_array()
array_search()
array_fill(), array_fill_keys(), range()
array_count_values()
array_column()
array_sum(), array_product()
count(), sizeof()
compact(), extract()
list() / [] destructuring: [$a, $b] = $arr

## OOP fundamentals

class, abstract class, interface, trait, enum
extends (single), implements (multiple), use (trait)
public, protected, private, readonly (PHP 8.1+)
static, final, abstract
$this, self::, static::, parent::
__construct(), __destruct()
Magic methods: __get, __set, __isset, __unset, __call, __callStatic, __toString, __invoke, __clone, __serialize, __unserialize, __sleep, __wakeup
Late static binding: static::
Object cloning: clone
Anonymous class: new class {}

## Interfaces / traits / enums

interface: method signatures, constants
abstract class: partial implementation
trait: horizontal code reuse, conflict resolution (insteadof, as)
Enum (PHP 8.1+): pure enum, backed enum (string|int)
Enum methods, interfaces on enum
Enum cases(), from(), tryFrom()
readonly class (PHP 8.2+)
readonly properties (PHP 8.1+)

## Modern PHP features

PHP 8.0: match, named arguments, nullsafe operator (?->), union types, attributes (#[...]), constructor promotion, str_contains/starts_with/ends_with, JIT
PHP 8.1: enums, fibers, readonly properties, intersection types, first-class callable syntax (strlen(...)), never type, array_is_list()
PHP 8.2: readonly classes, true/false/null as standalone types, DNF types (A&B)|C, deprecated dynamic properties
PHP 8.3: typed class constants, json_validate(), Randomizer additions, readonly amendment, #[\Override] attribute
PHP 8.4: property hooks (get/set), asymmetric visibility (public/private(set)), array_find/array_find_key, new without parens (new Foo()->method())

## Constructor promotion

class Point {
    public function __construct(
        public readonly float $x,
        public readonly float $y,
    ) {}
}

## Match expression

match($val) {
    1, 2 => 'one or two',
    3    => 'three',
    default => 'other',
}
// strict comparison, no fall-through, must be exhaustive or have default

## Named arguments

htmlspecialchars(string: $s, flags: ENT_QUOTES)
array_slice(array: $arr, offset: 1, length: 3, preserve_keys: true)

## Nullsafe operator

$city = $user?->getAddress()?->getCity();

## Fibers (PHP 8.1+)

Cooperative multitasking primitive
Fiber::suspend() — yield control
$fiber->start(), $fiber->resume(), $fiber->getReturn()
Used by async frameworks (ReactPHP, Revolt, Amp v3+)

## Generators

yield, yield $key => $value
yield from (delegation)
send() — send value into generator
getReturn() — final return value
Generator implements Iterator

## Closures & arrow functions

$fn = function($x) use (&$ref) { return $x + $ref; };
$fn = fn($x) => $x * 2; // arrow fn, auto-captures by value
Closure::bind(), Closure::bindTo(), Closure::fromCallable()
First-class callable: strlen(...), [$obj, 'method'](...)

## Error handling

Error hierarchy: Throwable → Error, Exception
Error: TypeError, ParseError, ArithmeticError, DivisionByZeroError, AssertionError
Exception: RuntimeException, LogicException, InvalidArgumentException, ...
try / catch (ExceptionType $e) / finally
catch (TypeA | TypeB $e) — catch multiple
set_exception_handler(), set_error_handler(), register_shutdown_function()
error_reporting(E_ALL), display_errors, log_errors
trigger_error() — manual E_USER_* errors

## Null handling

isset() — exists and not null
empty() — falsy check (0, "", "0", [], null, false)
?? (null coalescing): $a ?? $b ?? 'default'
??= (null coalescing assignment): $a ??= 'default'
is_null() vs === null

## Math functions

abs(), pow(), sqrt(), log(), log10(), exp()
min(), max()
random_int() — cryptographically secure
random_bytes() — secure random bytes
mt_rand() — Mersenne Twister (not cryptographic)
PHP_INT_MAX, PHP_INT_MIN, PHP_FLOAT_MAX, PHP_FLOAT_EPSILON

## Date / time

date(), date_create(), date_format()
DateTime, DateTimeImmutable, DateTimeInterface
DateInterval, DatePeriod
strtotime() — string to timestamp
time(), microtime(true)
Carbon (library): now(), parse(), diffForHumans(), add/sub fluent API
date_default_timezone_set(), new DateTimeZone()

## Filesystem / I/O

file_get_contents(), file_put_contents()
fopen(), fread(), fwrite(), fclose()
file(), readfile()
file_exists(), is_file(), is_dir(), is_readable(), is_writable()
mkdir(), rmdir(), rename(), copy(), unlink()
glob(), scandir(), opendir(), readdir()
realpath(), dirname(), basename(), pathinfo()
sys_get_temp_dir(), tempnam(), tmpfile()
Streams: stream_context_create(), fgets(), feof()

## Autoloading / namespaces

namespace App\Services;
use App\Models\User;
use App\Models\User as U;
spl_autoload_register()
Composer PSR-4 autoloading
class_exists(), interface_exists(), trait_exists()

## SPL (Standard PHP Library)

Data structures: SplStack, SplQueue, SplDoublyLinkedList, SplMinHeap, SplMaxHeap, SplFixedArray
Iterators: ArrayIterator, DirectoryIterator, RecursiveDirectoryIterator, RecursiveIteratorIterator
SplFileInfo, SplFileObject
Interfaces: Countable, Iterator, IteratorAggregate, ArrayAccess, Serializable
spl_autoload_register()

## Interfaces (built-in)

Countable — count()
Iterator — current, key, next, rewind, valid
IteratorAggregate — getIterator()
ArrayAccess — offsetGet/Set/Exists/Unset
Stringable — __toString()
JsonSerializable — jsonSerialize()

## Reflection API

ReflectionClass, ReflectionMethod, ReflectionProperty, ReflectionFunction
getAttributes() — read #[Attribute]
Used by DI containers, ORMs, serializers

## Attributes (PHP 8.0+)

#[Attribute]
#[Route('/api/users')]
#[Column(type: 'string')]
Reading: ReflectionClass::getAttributes()

## Serialization

serialize() / unserialize() — PHP native (security risk with unserialize)
json_encode() / json_decode()
json_encode flags: JSON_PRETTY_PRINT, JSON_UNESCAPED_UNICODE, JSON_THROW_ON_ERROR
JsonSerializable interface
__serialize/__unserialize (PHP 7.4+, preferred)

## Type checking functions

is_int(), is_float(), is_string(), is_bool(), is_null(), is_array(), is_object()
is_numeric() — "42", 42, 42.0
is_callable()
gettype() — returns string name
get_class(), instanceof, is_a()

## Performance / internals

OPcache — bytecode cache (opcache.enable, opcache.memory_consumption)
JIT (PHP 8.0+) — opcache.jit=tracing
Preloading — opcache.preload
memory_get_usage(), memory_get_peak_usage()
xdebug_time_index() / microtime()
PHP-FPM — process manager (static/dynamic/ondemand)
Execution models: FPM (shared-nothing), Octane (persistent), Swoole, FrankenPHP
