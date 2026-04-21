## Syntax basics

print("hello") — stdout-a çap
input("prompt: ") — stdin-dən oxu
type(x) — tipini qaytar
id(x) — yaddaş identifier
isinstance(x, int) — tip yoxlama
issubclass(C, P) — sinif irsiyyət
dir(x) — obyektin attributları
help(x) — docstring göstər
len(x) — uzunluq
range(start, stop, step) — ardıcıllıq generator
enumerate(it, start=0) — (index, value) cüt
zip(a, b) — paralel iterasiya
map(fn, it) / filter(fn, it) — lazy
reversed(seq) / sorted(it, key=..., reverse=True)
any(it) / all(it) — boolean reduce
sum(it, start=0) / min / max
abs / round(x, n) / pow(x, n, mod)

## Data types

int, float, complex — rəqəm
bool (int-in alt tipi)
str — immutable, unicode
bytes / bytearray — binary
list [1,2,3] — mutable ordered
tuple (1,2) — immutable
set {1,2} — unique unordered
frozenset — immutable set
dict {"k": v} — ordered (3.7+), hash map
None — null
NotImplemented / Ellipsis (...)
collections.OrderedDict / defaultdict / Counter / deque / namedtuple / ChainMap

## String operations

f"name={x!r}" — f-string (3.6+)
f"{x:>10.2f}" — format spec (alignment, precision)
"a,b,c".split(",") / "-".join(parts)
s.strip() / lstrip / rstrip
s.replace(old, new) / s.removeprefix("x") / removesuffix
s.startswith() / endswith()
s.upper() / lower() / title() / casefold()
s.find(sub) / index(sub) / count(sub)
s.format(a, b) — köhnə üsul
"%s %d" % (s, n) — C-style
str.encode("utf-8") / bytes.decode()
textwrap.dedent / fill
re modulu — regex

## Control flow

if / elif / else
match / case — structural pattern matching (3.10+)
for x in it: ... else: — for-else
while cond: ... else:
break / continue / pass
try / except / else / finally
raise ExceptionCls("msg") from other
with ctx as x: — context manager
assert cond, "msg" — -O ilə disable olur

## Functions

def fn(a, b=1, *args, c, **kwargs): — positional/default/varargs/keyword-only
lambda x: x+1 — anonim
fn.__name__ / __doc__ / __annotations__
def fn(x: int) -> str: — type hints
functools.partial(fn, arg)
functools.lru_cache(maxsize=128)
functools.cache (3.9+, unbounded)
functools.wraps — decorator üçün metadata saxla
functools.reduce(fn, it, init)
operator.itemgetter / attrgetter / methodcaller

## Classes / OOP

class Foo(Base): — inherit
def __init__(self, ...) — constructor
def __repr__ / __str__ / __eq__ / __hash__
@classmethod / @staticmethod
@property / @x.setter / @x.deleter
__slots__ = ("a", "b") — yaddaş optimizasiya
super().method() — parent call
abc.ABC / @abstractmethod — abstract class
Multiple inheritance + MRO (C3 linearization)
__enter__ / __exit__ — context manager protocol
__iter__ / __next__ — iterator
__call__ — obyekt-funksiya kimi
__getitem__ / __setitem__ / __len__

## Dataclasses

from dataclasses import dataclass, field
@dataclass(frozen=True, slots=True, kw_only=True)
field(default_factory=list)
dataclasses.asdict / astuple / replace
__post_init__ — əlavə setup
@dataclass(order=True) — müqayisə operatorları

## Typing (type hints)

from typing import Optional, Union, Any, Callable, Iterator, Iterable, Generator
list[int] / dict[str, int] / tuple[int, ...] (3.9+)
X | Y — union (3.10+)
Literal["a", "b"] / Final / ClassVar
TypedDict — dict struktur
NamedTuple (typing) — class-style
Protocol — structural subtyping (duck typing)
TypeVar / Generic[T]
ParamSpec / Concatenate — decorator typing
Self (3.11+), LiteralString
Annotated[int, "meta"]
cast(Type, x) — runtime no-op
@overload — function signature variants
TYPE_CHECKING — circular import guard

## Decorators

def dec(fn): def wrapper(*a, **kw): ... return wrapper
@dec — syntactic sugar
@dec(arg) — parametrized (wrapper factory)
Class decorator (məs. @dataclass)
contextlib.contextmanager — generator → ctx
contextlib.asynccontextmanager
contextlib.suppress(Exception)
contextlib.ExitStack — dinamik ctx

## Generators / iterators

def gen(): yield x — generator function
(x for x in it) — generator expression
yield from other_gen — delegation
next(gen, default)
itertools.chain / combinations / permutations / product
itertools.islice / takewhile / dropwhile / groupby
itertools.accumulate / count / cycle / repeat

## Comprehensions

[x*2 for x in range(10) if x%2] — list
{x for x in it} — set
{k: v for k, v in pairs} — dict
(x for x in it) — generator

## Context managers

with open("f") as fp: — fayl
with lock: — threading
Multiple: with A() as a, B() as b:
Nested: with ExitStack() as st: st.enter_context(...)

## Async / await

async def fn(): ...
await coro — coroutine-i gözlə
asyncio.run(main()) — event loop başlat
asyncio.gather(*coros) — paralel
asyncio.create_task(coro) — background task
asyncio.TaskGroup (3.11+) — structured concurrency
asyncio.Queue / Event / Lock / Semaphore
asyncio.wait_for(coro, timeout)
asyncio.sleep(n) — non-blocking
async for / async with
aiohttp / httpx / asyncpg / aioredis — async kitabxanalar
anyio — trio + asyncio abstraction

## Concurrency

threading.Thread / Lock / RLock / Event / Condition / Semaphore
queue.Queue / PriorityQueue / LifoQueue
multiprocessing.Process / Pool / Queue / Manager
concurrent.futures.ThreadPoolExecutor / ProcessPoolExecutor
executor.submit(fn) / map(fn, it) / as_completed
GIL — CPython-da bir anda bir thread bytecode icra edir
CPU-bound → multiprocessing; IO-bound → threading/asyncio
No-GIL build (PEP 703, 3.13+ experimental)

## Error handling

try / except Exception as e:
except (A, B) as e: — multi
raise from / raise from None — chain
ExceptionGroup / except* (3.11+)
traceback.format_exc() / print_exc()
sys.exc_info()
warnings.warn("deprecated", DeprecationWarning)

## Standard library highlights

os — path, env, process
os.path / pathlib.Path — müasir yol API
sys — argv, exit, stdin/stdout
io — StringIO / BytesIO
json — dumps / loads / JSONEncoder
csv — reader / DictReader / writer
pickle — serialize (güvənsiz untrusted üçün)
datetime — date / time / timedelta / timezone
zoneinfo — IANA tz (3.9+)
time — sleep, time, perf_counter, monotonic
random — randint, choice, shuffle, Random(seed)
secrets — cryptographically secure
hashlib — sha256, md5
hmac — message auth code
uuid — uuid4()
base64 / binascii
struct — binary pack/unpack
enum.Enum / IntEnum / Flag / auto()
decimal.Decimal — exact decimal
fractions.Fraction
statistics — mean, median, stdev
math — sqrt, log, sin, pi
logging — getLogger, basicConfig, handlers, formatters
argparse — CLI parser
click / typer — 3rd-party CLI
pathlib — Path("x") / "y"
tempfile — NamedTemporaryFile, TemporaryDirectory
shutil — copy, move, rmtree, which
subprocess.run / Popen / check_output
threading / multiprocessing / asyncio
urllib.request / urllib.parse
http.client / http.server
socket — low-level TCP/UDP
selectors — high-level select()
weakref — weak references
gc — garbage collector
inspect — introspection (getsource, signature)
copy.deepcopy / copy.copy

## Virtual envs / packaging

python -m venv .venv — venv yarat
source .venv/bin/activate — aktivləşdir
pip install <pkg> / pip install -r requirements.txt
pip install -e . — editable install
pip freeze > requirements.txt
pip-tools (pip-compile) — lock faylı
poetry — dependency + build tool
poetry add <pkg> / poetry install / poetry lock
uv — Rust-da yazılmış sürətli replacement
uv pip install / uv venv / uv sync
pipx — izolə CLI tool install
pyproject.toml — PEP 518/621 config
setup.py / setup.cfg — köhnə
wheel / sdist — distribution format
twine upload dist/* — PyPI-ə publish
build backend — hatchling, setuptools, poetry-core, flit-core

## Linting / formatting / type check

ruff — sürətli linter + formatter (replaces flake8/black/isort)
black — opinionated formatter
isort — import order
flake8 — PEP8 check (legacy)
pylint — deeper linter
mypy — static type checker
pyright / pylance — Microsoft TC
pyre — Meta TC
bandit — security linter
pre-commit — git hook runner

## Testing — pytest

pytest — test runner
pytest -k "name" — filter
pytest -m "slow" — marker
pytest -x — first fail-də dayan
pytest -v / -vv — verbose
pytest --lf — last failed only
pytest --ff — failed first
pytest -n 4 — xdist (parallel)
@pytest.fixture / @pytest.fixture(scope="session")
conftest.py — shared fixtures
@pytest.mark.parametrize("x,exp", [(1,2)])
@pytest.mark.skip / skipif / xfail
monkeypatch.setattr(...) — fixture
tmp_path / tmp_path_factory — fayl test
capsys / capfd — stdout capture
pytest-cov — coverage
pytest-mock — mock fixture
pytest-asyncio — async test
hypothesis — property-based testing
unittest — stdlib test framework
unittest.mock.patch / MagicMock / AsyncMock

## Django

django-admin startproject / startapp
python manage.py runserver / migrate / makemigrations
python manage.py createsuperuser / shell / dbshell
models.Model / fields (CharField, ForeignKey, ManyToMany)
QuerySet (lazy, chainable) — .filter / .exclude / .annotate / .select_related / .prefetch_related
Django ORM N+1 — select_related (FK) / prefetch_related (M2M, reverse)
views — FBV / CBV (ListView, DetailView, generic)
URLconf — path() / re_path() / include()
templates — {% %} tags, {{ }} vars
forms.Form / ModelForm
DRF (Django REST Framework) — Serializer, ViewSet, Router
admin.site.register(Model)
middleware — request/response pipeline
signals — post_save, pre_delete
settings.py — config
Django Channels — WebSocket / async
Celery — background tasks
django-debug-toolbar

## Flask

from flask import Flask, request, jsonify
app = Flask(__name__); @app.route("/path")
Blueprint — modular app
flask run / flask shell
request.args / request.json / request.form
Jinja2 templates
Flask-SQLAlchemy / Flask-Migrate / Flask-Login / Flask-WTF
url_for() / redirect() / abort(404)
before_request / after_request / teardown
session — signed cookie
app.config.from_object(...)

## FastAPI

from fastapi import FastAPI, Depends, HTTPException
app = FastAPI(); @app.get("/path")
Path / Query / Body / Header / Cookie
Pydantic model → request/response validation
async def endpoint(...) — native async
Depends(fn) — dependency injection
OAuth2PasswordBearer / JWT
BackgroundTasks — post-response task
uvicorn main:app --reload
starlette — altda olan ASGI framework
response_model / status_code / tags
Swagger UI — /docs; ReDoc — /redoc

## Pydantic

from pydantic import BaseModel, Field, field_validator, model_validator
class User(BaseModel): name: str; age: int = Field(ge=0)
model.model_dump() / model_dump_json() (v2)
model.model_validate(dict) / model_validate_json(str)
ConfigDict(str_strip_whitespace=True, extra="forbid")
Strict types — StrictStr / StrictInt
Computed fields — @computed_field
Pydantic v2 — Rust core (pydantic-core), çox sürətli
pydantic-settings — env-based config

## SQLAlchemy

engine = create_engine("postgresql://...", echo=True)
SessionLocal = sessionmaker(bind=engine)
declarative_base() / DeclarativeBase (2.0)
class User(Base): __tablename__ = "users"; id: Mapped[int] = mapped_column(primary_key=True)
relationship("Post", back_populates="author", lazy="selectin")
Core: select(User).where(...) / insert / update / delete
ORM: session.query() (1.x) vs session.execute(select(...)) (2.0)
session.add / commit / rollback / flush / refresh
Alembic — migration tool (alembic revision --autogenerate)
Eager loading — joinedload, selectinload, subqueryload

## HTTP clients

requests — sync, most popular
httpx — sync + async
urllib3 — low level
aiohttp — async client + server
requests.Session() — connection pool
httpx.AsyncClient — async
retry — tenacity kitabxanası

## Data / scientific

numpy — ndarray, vectorized
pandas — DataFrame / Series (read_csv, groupby, merge)
polars — Rust-based, faster than pandas
matplotlib / seaborn / plotly — visualization
scipy — scientific
scikit-learn — ML
pytorch / tensorflow — DL
jupyter / ipython — notebook
Dask / Ray — parallel compute

## Performance

cProfile — stdlib profiler
py-spy — sampling profiler (no code change)
scalene — CPU + memory profiler
line_profiler — line-by-line
memory_profiler — memory snapshots
tracemalloc — stdlib memory tracker
timeit — micro-benchmark
dis — disassembler (bytecode)
sys.getsizeof — object size
__slots__ — attribute dict remove (memory save)

## Internals / interview gotchas

Mutable default arg bug — def f(x=[]): ... shared!
Late binding closure — for i in range(3): lambdas.append(lambda: i)
is vs == — identity vs equality
Small int cache (-5..256) və string interning
GIL — CPython implementation detail, PyPy-də fərqli
Reference counting + cyclic GC
Duck typing — "if it quacks..."
LEGB scope — Local, Enclosing, Global, Built-in
nonlocal / global keywords
Walrus operator := (3.8+)
Positional-only / — (3.8+), keyword-only * 
PEP 8 style guide; PEP 20 Zen of Python
