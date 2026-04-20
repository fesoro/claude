package az.ecommerce.shared.application.bus;

/**
 * Laravel: src/Shared/Application/Bus/CommandBus.php (interface)
 * Spring: SimpleCommandBus impl bunu @Service kimi qeyd edir.
 *
 * Controller-də: commandBus.dispatch(new CreateOrderCommand(...));
 */
public interface CommandBus {

    <R> R dispatch(Command<R> command);
}
