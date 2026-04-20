package az.ecommerce.shared.application.middleware;

import az.ecommerce.shared.application.bus.Command;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.stereotype.Component;
import org.springframework.transaction.PlatformTransactionManager;
import org.springframework.transaction.support.TransactionTemplate;

/**
 * Pipeline mövqeyi: 4-cü.
 * Bütün command-i bir DB tranzaksiyaya bürüyür.
 * Laravel: TransactionMiddleware.php (DB::transaction çağırır)
 *
 * QEYD: 4 ayrı DB var, hansı TM seçməliyik?
 *  - Default olaraq UserTransactionManager (Primary).
 *  - Multi-DB transaction-ları üçün JTA / ChainedTransactionManager lazımdır.
 *  - Hələlik command-lər bir context-də işləyir, ona görə @Primary kifayət edir.
 */
@Component
public class TransactionMiddleware implements CommandMiddleware {

    private final TransactionTemplate transactionTemplate;

    /**
     * Default olaraq @Primary userTransactionManager seçilir.
     * Başqa context-də işləmək üçün handler-də @Transactional(transactionManager="...") istifadə edin.
     */
    public TransactionMiddleware(@Qualifier("userTransactionManager") PlatformTransactionManager transactionManager) {
        this.transactionTemplate = new TransactionTemplate(transactionManager);
    }

    @Override
    public <R> R handle(Command<R> command, CommandPipeline<R> next) {
        return transactionTemplate.execute(status -> next.proceed(command));
    }

    @Override
    public int order() {
        return 40;
    }
}
