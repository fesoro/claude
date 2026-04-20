package az.ecommerce.payment.infrastructure.web;

import az.ecommerce.payment.application.command.processpayment.ProcessPaymentCommand;
import az.ecommerce.payment.application.dto.PaymentDto;
import az.ecommerce.payment.application.query.getpayment.GetPaymentQuery;
import az.ecommerce.shared.application.bus.CommandBus;
import az.ecommerce.shared.application.bus.QueryBus;
import az.ecommerce.shared.infrastructure.api.ApiResponse;
import jakarta.validation.Valid;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.*;

import java.util.Map;
import java.util.UUID;

/**
 * Laravel: app/Http/Controllers/PaymentController.php
 *   POST /api/payments/process — auth + throttle:payment
 *   GET  /api/payments/{id}    — auth
 */
@RestController
@RequestMapping("/api/payments")
@PreAuthorize("isAuthenticated()")
public class PaymentController {

    private final CommandBus commandBus;
    private final QueryBus queryBus;

    public PaymentController(CommandBus commandBus, QueryBus queryBus) {
        this.commandBus = commandBus;
        this.queryBus = queryBus;
    }

    @PostMapping("/process")
    public ApiResponse<Map<String, UUID>> process(@RequestBody @Valid ProcessPaymentCommand cmd) {
        UUID id = commandBus.dispatch(cmd);
        return ApiResponse.success(Map.of("payment_id", id), "Ödəniş emal edildi");
    }

    @GetMapping("/{id}")
    public ApiResponse<PaymentDto> show(@PathVariable UUID id) {
        return ApiResponse.success(queryBus.ask(new GetPaymentQuery(id)));
    }
}
