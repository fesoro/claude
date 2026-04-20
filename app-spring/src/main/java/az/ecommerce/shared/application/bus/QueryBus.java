package az.ecommerce.shared.application.bus;

public interface QueryBus {
    <R> R ask(Query<R> query);
}
