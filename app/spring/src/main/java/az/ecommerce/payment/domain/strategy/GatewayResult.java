package az.ecommerce.payment.domain.strategy;

public record GatewayResult(boolean success, String transactionId, String errorMessage) {
    public static GatewayResult ok(String txId) {
        return new GatewayResult(true, txId, null);
    }
    public static GatewayResult fail(String error) {
        return new GatewayResult(false, null, error);
    }
}
