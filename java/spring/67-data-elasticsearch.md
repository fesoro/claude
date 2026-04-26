# 67 — Spring Data Elasticsearch — Geniş İzah

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [Elasticsearch nədir?](#elasticsearch-nədir)
2. [Spring Data Elasticsearch konfiqurasiyası](#spring-data-elasticsearch-konfiqurasiyası)
3. [Document mapping](#document-mapping)
4. [ElasticsearchRepository](#elasticsearchrepository)
5. [ElasticsearchOperations](#elasticsearchoperations)
6. [Axtarış sorğuları](#axtarış-sorğuları)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Elasticsearch nədir?

**Elasticsearch** — distributed, RESTful axtarış və analitika engine-i. Apache Lucene üzərindədir.

```
Elasticsearch istifadə sahələri:
  ├── Full-text search — məhsul axtarışı, log axtarışı
  ├── Log analytics — ELK stack (Elastic, Logstash, Kibana)
  ├── Real-time analytics — metrik dashboard
  ├── Autocomplete / suggestions
  └── Geo-spatial search

PostgreSQL vs Elasticsearch:
  PostgreSQL LIKE '%keyword%' → full table scan → yavaş
  Elasticsearch inverted index → O(1) keyword lookup → sürətli

Terminologiya:
  RDBMS     → Elasticsearch
  Database  → Index
  Table     → (Type — deprecated 7.x-dən)
  Row       → Document
  Column    → Field
  JOIN      → Nested / Parent-Child (məhduddur)
```

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-elasticsearch</artifactId>
</dependency>
```

---

## Spring Data Elasticsearch konfiqurasiyası

```yaml
# application.yml
spring:
  elasticsearch:
    uris: http://localhost:9200
    username: elastic
    password: changeme
    connection-timeout: 5s
    socket-timeout: 30s
    # SSL:
    # uris: https://localhost:9200
```

```java
// ─── Proqramatik konfigurasiya ────────────────────────
@Configuration
@EnableElasticsearchRepositories
public class ElasticsearchConfig extends ElasticsearchConfiguration {

    @Override
    public ClientConfiguration clientConfiguration() {
        return ClientConfiguration.builder()
            .connectedTo("localhost:9200")
            .withConnectTimeout(Duration.ofSeconds(5))
            .withSocketTimeout(Duration.ofSeconds(30))
            // Auth:
            .withBasicAuth("elastic", "changeme")
            // SSL:
            // .usingSsl()
            .build();
    }
}
```

---

## Document mapping

```java
// ─── @Document ────────────────────────────────────────
@Document(indexName = "products")
@Setting(settingPath = "/elasticsearch/product-settings.json")
public class ProductDocument {

    @Id
    private String id;

    @Field(type = FieldType.Text, analyzer = "standard")
    private String name;

    @Field(type = FieldType.Text, analyzer = "standard",
           searchAnalyzer = "standard")
    private String description;

    @Field(type = FieldType.Keyword) // exact match, not analyzed
    private String sku;

    @Field(type = FieldType.Keyword)
    private String category;

    @Field(type = FieldType.Double)
    private BigDecimal price;

    @Field(type = FieldType.Integer)
    private Integer stockQuantity;

    @Field(type = FieldType.Boolean)
    private boolean active;

    @Field(type = FieldType.Date, format = DateFormat.date_time)
    private Instant createdAt;

    // Nested object
    @Field(type = FieldType.Nested)
    private List<ProductAttributeDocument> attributes;

    // Geo point
    @GeoPointField
    private GeoPoint location;

    // Multi-field
    @MultiField(
        mainField = @Field(type = FieldType.Text, analyzer = "standard"),
        otherFields = {
            @InnerField(suffix = "keyword", type = FieldType.Keyword),
            @InnerField(suffix = "suggest", type = FieldType.Completion)
        }
    )
    private String brand;
}

public class ProductAttributeDocument {
    @Field(type = FieldType.Keyword)
    private String key;

    @Field(type = FieldType.Keyword)
    private String value;
}
```

```json
// /resources/elasticsearch/product-settings.json
{
  "index": {
    "number_of_shards": 3,
    "number_of_replicas": 1
  },
  "analysis": {
    "analyzer": {
      "az_analyzer": {
        "type": "custom",
        "tokenizer": "standard",
        "filter": ["lowercase", "az_stop", "az_stemmer"]
      }
    },
    "filter": {
      "az_stop": {
        "type": "stop",
        "stopwords": ["və", "ilə", "üçün", "bu", "bir"]
      }
    }
  }
}
```

---

## ElasticsearchRepository

```java
// ─── Repository interface ─────────────────────────────
@Repository
public interface ProductRepository
        extends ElasticsearchRepository<ProductDocument, String> {

    // Derived queries
    List<ProductDocument> findByCategory(String category);
    List<ProductDocument> findByActiveTrue();

    List<ProductDocument> findByPriceBetween(
        BigDecimal minPrice, BigDecimal maxPrice);

    // @Query ilə raw Elasticsearch query
    @Query("""
        {
          "bool": {
            "must": [
              { "match": { "name": "?0" } },
              { "term": { "active": true } }
            ]
          }
        }
        """)
    List<ProductDocument> searchByName(String name);

    // @Query ilə multi-field search
    @Query("""
        {
          "multi_match": {
            "query": "?0",
            "fields": ["name^3", "description", "brand^2"],
            "type": "best_fields",
            "fuzziness": "AUTO"
          }
        }
        """)
    SearchPage<ProductDocument> fullTextSearch(String query, Pageable pageable);

    // Count
    long countByCategory(String category);
}
```

---

## ElasticsearchOperations

```java
@Service
public class ProductSearchService {

    private final ElasticsearchOperations elasticsearchOperations;
    private final ProductRepository productRepository;

    // ─── Index document ───────────────────────────────
    public ProductDocument indexProduct(ProductDocument product) {
        return productRepository.save(product);
    }

    // ─── Bulk index ───────────────────────────────────
    public void bulkIndex(List<ProductDocument> products) {
        productRepository.saveAll(products);
    }

    // ─── Simple search ────────────────────────────────
    public SearchHits<ProductDocument> searchByQuery(String query) {
        Query searchQuery = NativeQuery.builder()
            .withQuery(q -> q.multiMatch(mm -> mm
                .query(query)
                .fields("name^3", "description", "brand^2")
                .fuzziness("AUTO")
                .type(TextQueryType.BestFields)
            ))
            .build();

        return elasticsearchOperations.search(
            searchQuery, ProductDocument.class);
    }

    // ─── Bool query ───────────────────────────────────
    public SearchHits<ProductDocument> searchProducts(ProductSearchRequest request) {
        var boolQuery = QueryBuilders.bool();

        // Must (AND)
        if (request.keyword() != null) {
            boolQuery.must(m -> m.multiMatch(mm -> mm
                .query(request.keyword())
                .fields("name^3", "description")
                .fuzziness("AUTO")
            ));
        }

        // Filter (no score impact)
        if (request.category() != null) {
            boolQuery.filter(f -> f.term(t -> t
                .field("category")
                .value(request.category())
            ));
        }

        if (request.minPrice() != null || request.maxPrice() != null) {
            boolQuery.filter(f -> f.range(r -> {
                r.field("price");
                if (request.minPrice() != null) r.gte(JsonData.of(request.minPrice()));
                if (request.maxPrice() != null) r.lte(JsonData.of(request.maxPrice()));
                return r;
            }));
        }

        boolQuery.filter(f -> f.term(t -> t
            .field("active").value(true)));

        Query query = NativeQuery.builder()
            .withQuery(q -> q.bool(boolQuery.build()))
            .withPageable(PageRequest.of(
                request.page(),
                request.size(),
                Sort.by(Sort.Direction.DESC, "_score")
            ))
            .withHighlightQuery(HighlightQuery.builder(
                Highlight.builder()
                    .fields(HighlightField.builder()
                        .name("name").build())
                    .build())
                .build())
            .build();

        return elasticsearchOperations.search(query, ProductDocument.class);
    }

    // ─── Autocomplete / Suggest ───────────────────────
    public List<String> suggestProductNames(String prefix) {
        SearchHits<ProductDocument> hits = elasticsearchOperations.search(
            NativeQuery.builder()
                .withQuery(q -> q.matchPhrasePrefix(m -> m
                    .field("name")
                    .query(prefix)
                    .maxExpansions(10)
                ))
                .withPageable(PageRequest.of(0, 10))
                .build(),
            ProductDocument.class
        );

        return hits.stream()
            .map(hit -> hit.getContent().getName())
            .distinct()
            .collect(Collectors.toList());
    }

    // ─── Geo search ───────────────────────────────────
    public List<ProductDocument> findNearby(double lat, double lon,
                                             double distanceKm) {
        SearchHits<ProductDocument> hits = elasticsearchOperations.search(
            NativeQuery.builder()
                .withQuery(q -> q.geoDistance(gd -> gd
                    .field("location")
                    .location(gl -> gl.latlon(ll -> ll.lat(lat).lon(lon)))
                    .distance(distanceKm + "km")
                ))
                .build(),
            ProductDocument.class
        );

        return hits.stream()
            .map(SearchHit::getContent)
            .collect(Collectors.toList());
    }
}
```

---

## Axtarış sorğuları

```java
@Service
public class AdvancedSearchService {

    private final ElasticsearchOperations operations;

    // ─── Aggregation — facets ─────────────────────────
    public SearchResult searchWithFacets(String keyword, Pageable pageable) {
        Query query = NativeQuery.builder()
            .withQuery(q -> q.multiMatch(mm -> mm
                .query(keyword)
                .fields("name", "description")))
            .withAggregation("categories", Aggregation.of(a -> a
                .terms(t -> t.field("category").size(20))))
            .withAggregation("priceRange", Aggregation.of(a -> a
                .range(r -> r
                    .field("price")
                    .ranges(
                        RangeAggregation.of(ra -> ra.to("100")),
                        RangeAggregation.of(ra -> ra.from("100").to("500")),
                        RangeAggregation.of(ra -> ra.from("500"))
                    )
                )))
            .withPageable(pageable)
            .build();

        SearchHits<ProductDocument> hits = operations.search(query, ProductDocument.class);

        // Aggregation nəticəsi
        ElasticsearchAggregations aggregations =
            (ElasticsearchAggregations) hits.getAggregations();

        Map<String, Long> categoryFacets = new HashMap<>();
        if (aggregations != null) {
            StringTermsAggregate categoryAgg =
                aggregations.aggregations().get("categories").sterms();

            categoryAgg.buckets().array().forEach(bucket ->
                categoryFacets.put(bucket.key().stringValue(), bucket.docCount())
            );
        }

        return new SearchResult(
            hits.getTotalHits(),
            hits.stream().map(SearchHit::getContent).collect(Collectors.toList()),
            categoryFacets
        );
    }

    // ─── Highlight — axtarış nəticəsini vurğula ───────
    public List<ProductSearchResult> searchWithHighlight(String keyword) {
        Query query = NativeQuery.builder()
            .withQuery(q -> q.multiMatch(mm -> mm
                .query(keyword)
                .fields("name", "description")
                .fuzziness("AUTO")
            ))
            .withHighlightQuery(HighlightQuery.builder(
                Highlight.builder()
                    .fields(
                        HighlightField.builder().name("name")
                            .parameters(p -> p.preTags("<em>").postTags("</em>"))
                            .build(),
                        HighlightField.builder().name("description")
                            .parameters(p -> p.numberOfFragments(3).fragmentSize(150))
                            .build()
                    )
                    .build())
                .build())
            .build();

        SearchHits<ProductDocument> hits = operations.search(query, ProductDocument.class);

        return hits.stream()
            .map(hit -> new ProductSearchResult(
                hit.getContent(),
                hit.getHighlightField("name"),
                hit.getHighlightField("description")
            ))
            .collect(Collectors.toList());
    }

    // ─── Sync — DB ilə Elasticsearch sinxronizasiya ───
    @EventListener
    public void onProductCreated(ProductCreatedEvent event) {
        ProductDocument doc = mapper.toDocument(event.getProduct());
        operations.save(doc);
    }

    @EventListener
    public void onProductUpdated(ProductUpdatedEvent event) {
        ProductDocument doc = mapper.toDocument(event.getProduct());
        operations.save(doc);
    }

    @EventListener
    public void onProductDeleted(ProductDeletedEvent event) {
        operations.delete(event.getProductId(), ProductDocument.class);
    }
}

record SearchResult(long total, List<ProductDocument> products,
                    Map<String, Long> categoryFacets) {}
record ProductSearchResult(ProductDocument product, List<String> nameHighlight,
                            List<String> descriptionHighlight) {}
```

---

## İntervyu Sualları

### 1. Elasticsearch niyə SQL LIKE-dan sürətlidir?
**Cavab:** Elasticsearch **inverted index** istifadə edir. Index yaradıldıqda hər sözün hansı document-lərdə olduğu xəritələnir. `"laptop"` axtarıldıqda birbaşa index-dən document ID-lər tapılır — O(1). SQL `LIKE '%laptop%'` → bütün cədvəl skan edilir — O(n). Həmçinin Elasticsearch distributed — axtarış paralel shard-larda icra edilir.

### 2. FieldType.Text vs FieldType.Keyword fərqi?
**Cavab:** `Text` — analyzed; tokenize edilir, lowercase, stop words; full-text search üçün. `"Apple MacBook Pro"` → `["apple", "macbook", "pro"]`. `Keyword` — not analyzed; tam dəyər; exact match, filter, aggregation, sort üçün. `"PENDING"` → `"PENDING"` (dəyişdirilmir). Adətən bir field həm `Text` (search), həm `keyword` (filter/sort) multi-field-dır.

### 3. Elasticsearch-də aggregation nədir?
**Cavab:** SQL GROUP BY + COUNT/SUM analoqudur. `Terms aggregation` — kateqoriyaya görə sayğac. `Range aggregation` — qiymət aralıqlarına görə bölmə. `Date histogram` — tarixə görə qrafik. `Nested aggregation` — nested field-lərdə. Axtarış nəticəsi ilə birlikdə cavab dönür — faceted search (filter side panel) üçün idealdır.

### 4. DB-Elasticsearch sinxronizasiyası necə edilir?
**Cavab:** (1) **Dual write** — DB-yə yazanda ES-ə də yaz; atom deyil, inconsistency riski. (2) **Event-driven** — domain event publish (`ProductCreatedEvent`) → event handler ES-ə yaz; daha ayrışmış, asenkron. (3) **Debezium CDC** — DB WAL oxuyur → Kafka → ES; zero application code. (4) **Scheduled sync** — DB-dən batch oxu → ES bulk index; lag var, amma güvənli. Production-da CDC (Debezium) + Kafka ən etibarlı yoldur.

### 5. Fuzziness nədir?
**Cavab:** Yazım səhvlərini dəstəkləmək üçün — "labtop" → "laptop" tapır. Levenshtein edit distance-ə əsaslanır. `"AUTO"` — sözün uzunluğuna görə avtomatik: 0-2 hərfli → exact match; 3-5 hərfli → 1 fərq; 6+ hərfli → 2 fərq. `fuzziness: 1` — maksimum 1 simvol fərq. Çox yüksək fuzziness = yanlış nəticələr + performans problemi.

*Son yenilənmə: 2026-04-10*
