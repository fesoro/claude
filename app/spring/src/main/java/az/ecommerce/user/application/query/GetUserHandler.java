package az.ecommerce.user.application.query;

import az.ecommerce.shared.application.bus.QueryHandler;
import az.ecommerce.shared.domain.exception.EntityNotFoundException;
import az.ecommerce.user.application.dto.UserDto;
import az.ecommerce.user.domain.repository.UserRepository;
import az.ecommerce.user.domain.valueobject.UserId;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

@Service
public class GetUserHandler implements QueryHandler<GetUserQuery, UserDto> {

    private final UserRepository repository;

    public GetUserHandler(UserRepository repository) {
        this.repository = repository;
    }

    @Override
    @Transactional(readOnly = true, transactionManager = "userTransactionManager")
    public UserDto handle(GetUserQuery query) {
        return repository.findById(new UserId(query.userId()))
                .map(UserDto::fromDomain)
                .orElseThrow(() -> new EntityNotFoundException("User", query.userId().toString()));
    }
}
