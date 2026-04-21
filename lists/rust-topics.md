## Basic syntax

let x = 5 — immutable binding
let mut x = 5 — mutable
const MAX: u32 = 100 — compile-time
static GLOBAL: u32 = 0 — 'static ömür
fn add(a: i32, b: i32) -> i32 { a + b }
let tuple: (i32, &str) = (1, "a")
let arr: [i32; 3] = [1, 2, 3]
let slice: &[i32] = &arr[0..2]
println! / eprintln! / format! / print! — macrolar
dbg!(expr) — debug print ilə value qaytarır

## Primitive types

i8 / i16 / i32 / i64 / i128 / isize — signed
u8 / u16 / u32 / u64 / u128 / usize — unsigned
f32 / f64 — float
bool / char (4-byte Unicode scalar)
() — unit type
!, never type (diverging)
str — unsized, həmişə &str kimi
String — heap-allocated, growable
&str — string slice (read-only)

## Ownership

Hər value-nun bir owner-i var
Scope-dan çıxanda drop olunur
let s2 = s1 — move (s1 artıq invalid)
Copy trait — implicit clone (integers, bool, tuples of Copy)
Clone trait — explicit .clone()
Drop trait — destructor

## Borrowing / references

&T — immutable reference (shared)
&mut T — mutable reference (exclusive)
Bir anda ya çoxlu & ya bir &mut (aliasing rule)
Reference lifetime > referent lifetime olmalı
Dereference — *r
Auto-deref coercion
Reborrow — &mut *r

## Lifetimes

&'a T — explicit lifetime
'static — bütün program
fn longest<'a>(x: &'a str, y: &'a str) -> &'a str
Lifetime elision rules — çox vaxt yazmağa ehtiyac yoxdur
'_ — anonymous lifetime
Struct-da — struct Foo<'a> { x: &'a str }
Higher-ranked — for<'a> Fn(&'a T)

## Structs / enums

struct Point { x: f64, y: f64 } — named
struct Pair(i32, i32) — tuple struct
struct Unit — unit struct
enum Option<T> { Some(T), None }
enum Result<T, E> { Ok(T), Err(E) }
enum Shape { Circle(f64), Rect { w: f64, h: f64 } }
Discriminant — C-like enum
#[repr(u8)] — memory layout

## Pattern matching

match x { 1 => "one", 2..=5 => "few", _ => "other" }
if let Some(v) = opt { ... }
while let Some(v) = iter.next() { ... }
let Some(v) = opt else { return; } — let-else
Destructuring — let Point { x, y } = p
@ binding — x @ 1..=5
Guards — pattern if cond
| — or patterns

## Option / Result

Option<T> — Some(T) | None
opt.unwrap() / .unwrap_or(default) / .unwrap_or_else(|| ...) / .expect("msg")
opt.map / and_then / or / or_else / filter / take
opt.is_some() / is_none()
Result<T, E> — Ok(T) | Err(E)
res.map / map_err / and_then (like flatMap)
res? — early return on Err (propagate)
? operator — also unwraps Option (with From<NoneError>)
anyhow::Result — ergonomic error type
thiserror::Error — custom error derive

## Error handling

panic!("msg") — unrecoverable
unreachable!() / todo!() / unimplemented!()
Result + ? — idiomatic recoverable error
std::error::Error trait
Box<dyn Error> — erased error
anyhow — runtime error with context
thiserror — library error derive
color-eyre — pretty panic/error

## Traits

trait Animal { fn name(&self) -> &str; }
impl Animal for Dog { fn name(&self) -> &str { "dog" } }
Default methods — trait-da implement
Associated type — type Item;
Generic trait — trait Foo<T>
Bounds — fn f<T: Trait1 + Trait2>(x: T)
where clause — where T: Trait, U: Trait
Trait object — dyn Trait (dynamic dispatch)
impl Trait — opaque return / argument (static)
Blanket impl — impl<T: Display> ToString for T
Supertrait — trait Child: Parent

## Common traits

Debug — {:?} format
Display — {} format
Clone / Copy
PartialEq / Eq / PartialOrd / Ord
Hash — for HashMap/HashSet key
Default — ::default()
Iterator — next()
IntoIterator / FromIterator
From / Into / TryFrom / TryInto
AsRef / AsMut / Borrow / BorrowMut
Deref / DerefMut — smart pointer
Drop — destructor
Send / Sync — thread safety markers
Sized / ?Sized — DST

## Smart pointers

Box<T> — heap allocation, unique owner
Rc<T> — reference counted (single thread)
Arc<T> — atomic RC (thread safe)
Cell<T> — interior mutability (Copy types)
RefCell<T> — runtime borrow check (single thread)
Mutex<T> — lock-based mutation (Send+Sync)
RwLock<T> — reader-writer lock
Cow<'a, T> — clone-on-write (Borrowed / Owned)
Weak<T> — non-owning (break Rc/Arc cycle)
Pin<P> — prevent moving (async, self-ref)

## Collections

Vec<T> — growable array
VecDeque<T> — double-ended queue
LinkedList<T> — doubly linked (rarely useful)
HashMap<K, V> / HashSet<T> — hash-based (SipHash, DoS-safe)
BTreeMap<K, V> / BTreeSet<T> — ordered
BinaryHeap<T> — max-heap
String — UTF-8 owned
&str — string slice
Slice — &[T] / &mut [T]

## Iterators

.iter() — &T
.iter_mut() — &mut T
.into_iter() — T
.map / .filter / .flat_map / .filter_map / .take / .skip
.fold(init, |acc, x| ...) / .reduce / .sum / .product
.count / .min / .max / .any / .all
.collect::<Vec<_>>() / into HashMap
.zip / .chain / .enumerate / .rev / .cloned / .copied
.chunks(n) / .windows(n)
.peekable() / .take_while / .skip_while
.step_by(n)
.position / .find / .find_map
Custom iterator — impl Iterator { type Item; fn next() }

## Closures

|x| x + 1 — inferred
|x: i32| -> i32 { x + 1 } — explicit
Fn — immutable borrow (call multiple times)
FnMut — mutable borrow
FnOnce — takes ownership (once)
move |x| — force capture by move
Box<dyn Fn(i32) -> i32> — heap-allocated

## Concurrency

std::thread::spawn(|| { ... })
thread::scope — scoped threads (1.63+)
Arc<Mutex<T>> — shared mutable state
Arc<RwLock<T>> — many readers or one writer
std::sync::mpsc — multi-producer single-consumer channel
crossbeam::channel — MPMC, faster
std::sync::atomic — AtomicUsize, AtomicBool (Ordering::SeqCst/Acquire/Release/Relaxed)
std::sync::Barrier / Condvar / Once / OnceLock
rayon — data parallelism (.par_iter())
Send — T thread-ə göndərilə bilər
Sync — &T thread-ə göndərilə bilər

## Async / await

async fn fetch() -> Result<T> { ... } — Future qaytarır
.await — poll Future
tokio — most popular runtime
#[tokio::main] async fn main() { ... }
tokio::spawn — background task
tokio::select! — multiple futures wait
tokio::join!(a, b) — wait all
tokio::time::sleep / timeout / interval
tokio::sync::Mutex / RwLock / Semaphore / Notify / oneshot / mpsc / broadcast / watch
async-std — alternative runtime
smol — lightweight runtime
futures crate — utilities (StreamExt, SinkExt, join_all)
Stream — async Iterator
Pin / Unpin / self-referential
Runtime color — async infects call stack

## Macros

println! / vec! / format! — declarative
macro_rules! my_macro { (...) => { ... } } — declarative
#[derive(Debug, Clone)] — derive macro
#[proc_macro] / #[proc_macro_attribute] / #[proc_macro_derive] — procedural
syn crate — parse Rust token stream
quote crate — generate Rust tokens
cargo expand — macro-ları görünüşünü göstər

## Modules / crates

mod foo; — declare module
pub mod / pub(crate) / pub(super) — visibility
use path::to::Thing — bring into scope
use path as alias
crate::... — crate root
super::... — parent module
self::... — current
extern crate — legacy (edition 2015)
Cargo.toml — manifest
[dependencies] / [dev-dependencies] / [build-dependencies]
[features] — conditional compile
#[cfg(feature = "x")] — feature gate
[workspace] — monorepo

## Cargo

cargo new <name> / cargo new --lib <name>
cargo init — mövcud folder-də
cargo build / cargo build --release
cargo run / cargo run --release -- args
cargo check — compile without codegen (sürətli)
cargo test / cargo test --doc / cargo test -- --nocapture
cargo bench — nightly benchmark (və ya criterion)
cargo fmt — rustfmt
cargo clippy — lint
cargo clippy -- -D warnings — strict
cargo doc --open — docs build + open
cargo add / cargo remove — dependency
cargo update — Cargo.lock yenilə
cargo publish — crates.io
cargo install <bin> — binary install
cargo tree — dependency tree
cargo-edit / cargo-watch / cargo-expand / cargo-udeps / cargo-audit — əlavə tool-lar
target/ — build output
Cargo.lock — reproducible build

## Testing

#[test] fn it_works() { assert_eq!(2+2, 4); }
#[cfg(test)] mod tests { ... }
assert! / assert_eq! / assert_ne!
#[should_panic(expected = "msg")]
#[ignore] — skip by default
Integration tests — tests/ directory
Doc tests — /// ``` rust ... ``` in comments
mockall — mock framework
proptest / quickcheck — property testing
criterion — benchmark
insta — snapshot testing

## Unsafe

unsafe { ... } — block / fn
Raw pointer — *const T / *mut T
Dereference raw pointer
FFI — extern "C" { fn c_fn(); }
Undefined behavior oldu — sən məsulsan
std::mem::transmute — bit-level reinterpret (təhlükəli)
std::ptr::read/write
MaybeUninit<T>

## Common crates

serde / serde_json / serde_yaml — serialization
tokio / async-std — async runtime
reqwest — HTTP client
hyper — low-level HTTP
axum — web framework (on hyper+tower)
actix-web — web framework (actor model)
rocket — ergonomic web framework
warp — filter-based web
tonic — gRPC (on hyper+prost)
prost — protobuf
sqlx — async SQL (compile-time checked)
diesel — sync ORM (macro-based)
sea-orm — async ORM
redis — Redis client
mongodb — MongoDB client
clap — CLI parser (derive or builder)
anyhow / thiserror — errors
tracing / tracing-subscriber — logging/telemetry
log — logging facade
env_logger / pretty_env_logger
chrono / time — date/time
uuid — UUIDs
regex — regex engine
rayon — data parallelism
crossbeam — concurrency primitives
dashmap — concurrent HashMap
once_cell / lazy_static (legacy)
parking_lot — faster Mutex/RwLock
rand — randomness
bytes — byte buffer
tower / tower-http — service abstraction (middleware)

## Memory / performance

Zero-cost abstraction — iterator, async, generics
Monomorphization — generics inline per type
dyn Trait — vtable lookup (dynamic dispatch)
Box<dyn T> — heap + vtable
Inlining — #[inline] / #[inline(always)]
#[repr(C)] — C-compatible layout
Stack vs heap
String vs &str — owned vs borrowed
Vec<T> vs &[T]
Cow — avoid unnecessary clone
Arc vs Rc — sync vs !Sync

## Edition / toolchain

Edition 2015 / 2018 / 2021 / 2024 — breaking-change epochs
rustc — compiler
rustup — toolchain installer/switcher
rustup toolchain install stable/beta/nightly
rustup default stable
rustup component add rustfmt clippy rust-analyzer
rust-analyzer — LSP
miri — UB detector (nightly)
cross — cross-compilation helper
