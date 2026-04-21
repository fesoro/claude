package domain

// Specification — composable biznes qaydası
//
// Laravel: Specification.php — and(), or(), not()
// Spring: Specification<T> functional interface (lambda dəstəyi)
// Go: generic type alias + function type — ən idiomatik approach
type Specification[T any] interface {
	IsSatisfiedBy(candidate T) bool
}

// SpecFunc — funksional adapter (Java lambda kimi)
//
// İstifadə:
//   isPositive := SpecFunc[int](func(n int) bool { return n > 0 })
type SpecFunc[T any] func(T) bool

func (f SpecFunc[T]) IsSatisfiedBy(c T) bool { return f(c) }

// And — iki spec birləşdirir
func And[T any](a, b Specification[T]) Specification[T] {
	return SpecFunc[T](func(c T) bool { return a.IsSatisfiedBy(c) && b.IsSatisfiedBy(c) })
}

// Or — iki spec-dən hər hansı biri
func Or[T any](a, b Specification[T]) Specification[T] {
	return SpecFunc[T](func(c T) bool { return a.IsSatisfiedBy(c) || b.IsSatisfiedBy(c) })
}

// Not — spec-i invert edir
func Not[T any](s Specification[T]) Specification[T] {
	return SpecFunc[T](func(c T) bool { return !s.IsSatisfiedBy(c) })
}
