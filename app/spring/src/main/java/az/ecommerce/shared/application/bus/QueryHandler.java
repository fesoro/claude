package az.ecommerce.shared.application.bus;

public interface QueryHandler<Q extends Query<R>, R> {
    R handle(Q query);
}
