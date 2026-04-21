## Basic types

string / number / boolean / bigint / symbol
null / undefined / void / never / unknown / any
Array<T> / T[] — array
[string, number] — tuple
readonly T[] / ReadonlyArray<T>
object / {} — non-primitive
Record<K, V> — dict
enum / const enum — enumeration
literal types — "a" | "b" | 42 | true

## Variables / declarations

let x: number = 1 — block scope, mutable
const x = 1 — block scope, readonly
var — köhnə, function scope (istifadə etmə)
const foo = {} as const — deep readonly literal
declare const x: number — ambient
export / import — ESM
import type { X } from "..." — type-only import
export type { X } — type-only export

## Functions

function fn(a: number, b = 0): string { ... }
const fn = (a: number): string => { ... } — arrow
(a: number, b?: string) => void — optional param
(...args: number[]) => void — rest
function fn<T>(x: T): T — generic
Overloads — function fn(a: string): string; function fn(a: number): number;
this parameter — function fn(this: Foo) { ... }

## Interfaces / type aliases

interface User { name: string; age?: number; readonly id: string }
interface extends — interface A extends B, C
interface merging — same name birləşir (declaration merging)
type User = { ... } — type alias
type A = B & C — intersection
type A = B | C — union
type X = keyof User — property names
type X = User["name"] — indexed access
type X = typeof value — value-dən tip çıxar

## Union & narrowing

type Result = Success | Error
typeof guard — if (typeof x === "string")
instanceof guard — if (x instanceof Date)
in guard — if ("foo" in obj)
Discriminated union — { kind: "a"; a: T } | { kind: "b"; b: U }
exhaustiveness — default: const _: never = x
User-defined type guard — function isFoo(x): x is Foo
Assertion function — function assert(c: boolean): asserts c

## Generics

function fn<T>(x: T): T
class Box<T> { value: T }
<T extends Lengthwise> — constraint
<T, U extends keyof T> — dependent
Default type — <T = string>
Variadic tuple — <T extends unknown[]>
Conditional generic — T extends U ? X : Y

## Utility types

Partial<T> / Required<T> / Readonly<T>
Pick<T, K> / Omit<T, K>
Record<K, V>
Exclude<T, U> / Extract<T, U>
NonNullable<T>
ReturnType<F> / Parameters<F> / ConstructorParameters<F> / InstanceType<C>
Awaited<T> — unwrap Promise
ThisParameterType<F> / OmitThisParameter<F>
Uppercase<S> / Lowercase<S> / Capitalize<S> / Uncapitalize<S> — string literal

## Mapped types

{ [K in keyof T]: T[K] } — identity
{ readonly [K in keyof T]?: T[K] } — modifiers
{ -readonly [K in keyof T]-?: T[K] } — remove modifiers
{ [K in keyof T as NewKey]: T[K] } — key remapping (4.1+)

## Conditional types

T extends U ? X : Y
infer — T extends (infer R)[] ? R : never
Distributive — union T üçün ayrı-ayrı hesablanır
[T] extends [U] — non-distributive trick

## Template literal types

type Greeting = `hello ${string}`
type EventName<T extends string> = `on${Capitalize<T>}`
type CSSVar = `--${string}`
Combined with mapped — { [K in keyof T as `get${Capitalize<K>}`]: () => T[K] }

## Classes

class Foo { private x: number; protected y: string; public z }
#privateField — ECMAScript private (runtime)
readonly field
static method() {}
abstract class / abstract method
constructor(public name: string) — parameter property
implements Interface
extends BaseClass
super.method() / super(args)
get / set — accessor
@decorator — TS 5.0 standard decorator (Stage 3)

## Enums

enum Color { Red, Green, Blue } — numeric
enum Dir { Up = "UP", Down = "DOWN" } — string
const enum — inline at compile time
enum reverse mapping — Color[0] === "Red"
Alternatively: const Color = { Red: "R" } as const; type Color = typeof Color[keyof typeof Color]

## Modules / declaration files

.d.ts — tiplər üçün
declare module "x" { ... } — ambient module
declare namespace X { ... }
@types/xxx — DefinitelyTyped
triple-slash /// <reference path="..." />
module augmentation — 3rd-party modul genişləndirmə

## tsconfig.json highlights

target — ES output (ES2022, ESNext)
module — ESNext / CommonJS / NodeNext
moduleResolution — node / bundler / nodenext
strict — strictNullChecks / noImplicitAny / strictFunctionTypes və s.
noUnusedLocals / noUnusedParameters
noImplicitReturns / noFallthroughCasesInSwitch
exactOptionalPropertyTypes
lib — ["ES2022", "DOM"]
jsx — react / react-jsx / preserve
paths / baseUrl — path alias
rootDir / outDir / declaration / declarationMap / sourceMap
allowJs / checkJs
isolatedModules — Babel / esbuild uyğun
skipLibCheck — .d.ts skip
esModuleInterop / allowSyntheticDefaultImports
verbatimModuleSyntax (5.0+)

## ESM / CommonJS

ESM — import / export, .mjs, "type": "module"
CJS — require() / module.exports, .cjs
Node resolution — conditional exports, package.json "exports"
import.meta.url / import.meta.resolve
Dynamic import() — async modul yüklə
tsx / ts-node / bun — direct TS exec

## Package managers

npm — default, lock: package-lock.json
pnpm — content-addressable, symlink, sürətli + disk qənaət
yarn — v1 (classic), v2+ (berry, PnP)
bun — all-in-one JS runtime + PM
npx — binary run without install
npm ci — lockfile-dən təmiz install
npm audit / npm outdated
Workspaces / monorepo — npm workspaces, pnpm workspaces, yarn workspaces
Turborepo / Nx — monorepo build orchestrator
Lerna — legacy monorepo

## React (with TS)

const Comp: React.FC<Props> = ({ x }) => <div>{x}</div>
useState<T>(initial) — state
useEffect(() => { ... return cleanup }, [deps])
useMemo / useCallback — memoization
useRef<HTMLDivElement>(null) — DOM ref
useContext / createContext
useReducer — Redux pattern local
useImperativeHandle — ref expose
useLayoutEffect / useSyncExternalStore / useTransition / useDeferredValue
useId — stable unique ID
React.memo(Component) — props compare
forwardRef<Ref, Props>(...)
Suspense / lazy(() => import("..."))
Server Components (RSC) / "use client" / "use server"
Props children — React.ReactNode
Event — React.ChangeEvent<HTMLInputElement>
Zustand / Jotai / Redux Toolkit / TanStack Query — state libs

## Next.js

App Router — app/ directory (13+)
Pages Router — pages/ directory (legacy)
Server Components default; "use client" for client
layout.tsx / page.tsx / loading.tsx / error.tsx / not-found.tsx
generateStaticParams — SSG dynamic route
generateMetadata — head tags
Route handlers — app/api/.../route.ts (GET/POST)
Middleware — middleware.ts
Server Actions — async form action with "use server"
Image / Link / Font / Script components
getServerSideProps / getStaticProps (pages router)
ISR — revalidate: n
Dynamic rendering — noStore() / cookies() / headers()
next/navigation — useRouter, usePathname, useSearchParams
Turbopack — dev bundler

## NestJS

@Module({ imports, controllers, providers })
@Controller("path") / @Get / @Post / @Param / @Body / @Query
@Injectable() — service
Dependency injection — constructor param
@UseGuards / @UseInterceptors / @UsePipes / @UseFilters
DTOs + class-validator + class-transformer
Pipes — validation, transform (ValidationPipe)
Interceptors — cross-cutting logic
Guards — auth
Exception filters
Microservices — TCP, Redis, Kafka, gRPC, NATS
TypeORM / Prisma / Mongoose integration
@nestjs/config, @nestjs/schedule, @nestjs/bull (queue)
main.ts — bootstrap(app)

## Express

import express from "express"; const app = express()
app.get("/path", (req, res) => res.json({})); app.listen(3000)
Middleware — (req, res, next) => next()
express.json() / express.urlencoded()
cors / helmet / morgan / compression
Router — const router = express.Router()
Error handler — (err, req, res, next)
Request<Params, ResBody, ReqBody, Query> types
@types/express required

## ORM / DB

Prisma — schema.prisma, prisma generate, prisma migrate dev
prisma.user.findMany({ where, include, select })
Drizzle ORM — SQL-like TS API, no codegen needed
TypeORM — decorator-based (legacy Nest default)
Kysely — type-safe SQL query builder
MikroORM — unit of work pattern
Mongoose — MongoDB ODM

## Testing

Vitest — Vite-native, fast, Jest API compatible
Jest — classic, widely used
describe / it / test / expect
expect(x).toBe / toEqual / toMatchObject / toThrow
vi.fn() / jest.fn() — mock function
vi.mock("...") / jest.mock(...)
beforeAll / beforeEach / afterAll / afterEach
Snapshot — toMatchSnapshot / toMatchInlineSnapshot
@testing-library/react — render, screen.getBy*, userEvent
Playwright / Cypress — E2E
supertest — HTTP test
msw — API mock (service worker)

## Lint / format

ESLint — linter (flat config eslint.config.js)
@typescript-eslint/parser + @typescript-eslint/eslint-plugin
typescript-eslint/stylistic-type-checked
Prettier — formatter (.prettierrc)
eslint-config-prettier — conflict off
Biome — Rust-based lint+format (ESLint + Prettier replacement)
oxlint — Rust-based ESLint alternative
dprint — Rust formatter

## Build tools / bundlers

tsc — official compiler
Vite — dev server + Rollup production
esbuild — Go-based, super fast
swc — Rust compiler (Next.js uses)
Webpack — classic bundler
Rollup — library-friendly
Parcel — zero-config
tsup — esbuild-based library bundler
Rolldown — Rust Rollup (future Vite core)
Turbopack — Vercel Rust bundler

## Decorators (Stage 3)

@dec class Foo {} — class decorator
@dec method() {} — method decorator
@dec field — field decorator
experimentalDecorators — köhnə TS decorator (NestJS, TypeORM hələ də istifadə edir)
emitDecoratorMetadata — reflect-metadata üçün

## Misc / advanced

Branded types — type UserId = string & { __brand: "UserId" }
Opaque types — simulate nominal typing
satisfies operator (4.9+) — const x = { ... } satisfies Type
const type parameters (5.0+) — fn<const T>(x: T)
using keyword (5.2+) — resource management (Symbol.dispose)
Tuple labels — [name: string, age: number]
Variadic tuple — [...T, number]
Recursive types — type JSON = string | number | boolean | null | JSON[] | { [k: string]: JSON }
PropertyKey — string | number | symbol
Awaitable — T | Promise<T>
NoInfer<T> (5.4+) — prevent inference
const type parameter / satisfies / swc compiler
tsx files — JSX enabled TS
