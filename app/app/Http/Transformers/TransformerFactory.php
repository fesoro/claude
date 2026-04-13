<?php

declare(strict_types=1);

namespace App\Http\Transformers;

/**
 * TRANSFORMER FACTORY
 * ====================
 * API versiyasına görə düzgün Transformer class-ını seçir.
 *
 * NECƏ İŞLƏYİR?
 * 1. Request-dən API versiyasını al (EnsureApiVersion middleware təyin edir)
 * 2. Versiyaya uyğun transformer class-ını tap
 * 3. Class-ı yaradıb qaytar
 *
 * NÜMUNƏ:
 * TransformerFactory::make('product')
 *   → API v1 → V1\ProductTransformer
 *   → API v2 → V2\ProductTransformer
 *
 * YENİ VERSİYA ƏLAVƏ ETMƏK:
 * 1. app/Http/Transformers/V3/ qovluğu yarat
 * 2. ProductTransformer, OrderTransformer class-ları yarat
 * 3. Bu factory avtomatik tapacaq (convention-based)
 */
class TransformerFactory
{
    /**
     * Verilən entity tipi üçün versiyaya uyğun transformer qaytar.
     *
     * @param string $entity Entity adı: 'product', 'order', 'user', 'payment'
     * @param string|null $version API versiyası: 'v1', 'v2'. null = config-dan oxu
     * @return TransformerInterface
     */
    public static function make(string $entity, ?string $version = null): TransformerInterface
    {
        $version = $version ?? config('api.version', 'v1');

        // Convention: V1\ProductTransformer, V2\OrderTransformer
        $versionNamespace = strtoupper(substr($version, 0, 1)) . substr($version, 1); // v1 → V1
        $entityName = ucfirst($entity); // product → Product

        $class = "App\\Http\\Transformers\\{$versionNamespace}\\{$entityName}Transformer";

        if (!class_exists($class)) {
            // Versiya tapılmazsa v1-ə fallback et
            $fallbackClass = "App\\Http\\Transformers\\V1\\{$entityName}Transformer";

            if (!class_exists($fallbackClass)) {
                throw new \RuntimeException(
                    "Transformer tapılmadı: {$entity} (versiya: {$version}). " .
                    "Yoxlanılan class-lar: {$class}, {$fallbackClass}"
                );
            }

            return new $fallbackClass();
        }

        return new $class();
    }
}
