package az.ecommerce.order.application.dto;

import jakarta.validation.constraints.NotBlank;

public record AddressDto(
        @NotBlank String street,
        @NotBlank String city,
        @NotBlank String zip,
        @NotBlank String country
) {}
