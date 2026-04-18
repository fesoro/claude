# Estimation

## Niyə vacibdir? (Why it matters)
Estimation senior mühəndislikdəki ən çətin və ən görünən bacarıqlardan biridir. Estimatları davamlı qaçırsan, PM və rəhbərlik qarşısında etibarını itirirsən — kodun nə qədər yaxşı olmasından asılı olmayaraq. Çox şişirdilmiş estimatlar biznesi yavaşladır. Sıx estimatlar komandanı yandırır. Bunu davamlı şəkildə idarə edən senior mühəndis daha böyük layihələrə etibar qazanır.

Senior mühəndislər üçün əsas dəyişiklik "estimate" və "commitment"-in fərqli şeylər olduğunu başa düşməkdir. Səhv sözü istifadə etmək sənə aylar güvən itkisi maliyyətinə başa gələ bilər.

## Yanaşma (Core approach)
1. **Rough estimate vs commitment — dil vacibdir.** Rough estimate "my gut says 2-3 weeks"-dir. Commitment "we will ship by the 15th"-dir. Rough estimate-in təsadüfən commitment-ə çevrilməsinə heç vaxt icazə vermə.
2. **Bilinməyənlər üçün dürüstcəsinə buffer əlavə et.** Orta mürəkkəblikdə iş üçün 30-50% buffer. Real bilinməyənləri olan iş üçün daha çox.
3. **Unknown unknowns research spike tələb edir.** Bir şeyi ən azı scope edə bilmirsənsə, bunu de və estimate-dən əvvəl müəyyən vaxta bağlanmış araşdırma təklif et.
4. **Hissələrə böl.** Böyük estimatlar həmişə səhvdir. 1-3 günlük sub-task-lara böl. Hər birini estimate et. Üstünə buffer əlavə et.
5. **İzlə və öyrən.** Harada və nə qədər səhv etdiyin barədə qeyd saxla. Gələcək estimatların məlumatla yaxşılaşır.

## Konkret skript və template (Scripts & templates)

### T-shirt sizing (sürətli rough estimate-lər)
- **XS** — bir gündən az
- **S** — 1-3 gün
- **M** — 1 həftə
- **L** — 2-3 həftə
- **XL** — 4-6 həftə
- **XXL** — "I can't estimate this until I investigate"

Bunu erkən product söhbətləri üçün istifadə et. Commit etmədən gözləntini təyin edir.

### PERT (üç nöqtəli estimate)
Hər vacib task üçün:
- Optimistic (O): hər şey mükəmməl gedir
- Realistic (R): ən ehtimallı
- Pessimistic (P): işlər səhv gedir
- Ağırlıqlı estimate: (O + 4R + P) / 6

Nümunə:
- O = 3 gün, R = 5 gün, P = 10 gün
- Estimate = (3 + 20 + 10) / 6 = 5.5 gün. 6 gün üçün planla. 5-10 diapazonu göstər.

### Skript: research spike istəmək
> "I can't estimate this accurately yet. Too many unknowns around [X]. Can I spend 2 days investigating and come back with a proper estimate? That way we don't commit to something we regret."

### Skript: rough estimate vermək
> "Rough estimate — this feels like 2-3 weeks, but I want to be clear this is not a commitment. Once I've scoped it properly I'll give you a firmer number. Good to proceed with this as a planning assumption?"

### Skript: commitment vermək
> "I commit to shipping by the end of March. Risks I'm holding: [X, Y]. If those materialize, I'll flag you within a week so we can replan. Otherwise — March 31st."

### Skript: layihə ortasında estimate-i yeniləmək
> "Update on [project]. Original estimate was 3 weeks. I'm now seeing it's more like 5. The reason: [specific unknown that became known]. New target: [date]. What's the impact on your side?"

### Skript: qeyri-real deadline-a etiraz
> "The timeline you're asking for is 2 weeks. My honest estimate is 5 weeks. Options:
> 1. 5 weeks, full scope.
> 2. 2 weeks, cut [X, Y, Z] — a smaller v1.
> 3. 2 weeks, borrow [engineer] from the other team — becomes 3 weeks with ramp-up.
> Which fits your priority?"

### Story points (agile konteksti)
1 point = cüzi, 2 = sadə, 3 = orta, 5 = mürəkkəb, 8 = böyük, 13 = çox böyük, hissələrə böl.

Point-ləri saata çevirmə. Point-lər nisbi mürəkkəblikdir, vaxt deyil.

### Bilinməyənlərlə işi estimate etmək — açıq bilinməyənlər siyahısı
```
Task: Migrate order API to new schema
Estimate: 3-5 weeks

Unknowns:
- Data volume in production (impacts migration strategy) — spike needed
- Downstream consumers (who calls us?) — 1 day investigation
- Rollback plan (need DBA input) — meeting needed

If unknowns resolve well: 3 weeks.
If they don't: could be 8+ weeks.
```

## Safe phrases for B1 English speakers
- "My rough estimate is..." — qeyri-müəyyənliyi göstərmək
- "This is not a commitment yet." — aydın çərçivələmə
- "I need X days of investigation before I can commit." — spike istəmək
- "Here's a range — best case / worst case." — diapazon estimate
- "If [unknown] goes well, X. If not, Y." — şərti
- "I'll commit to this date once I've scoped it." — commit-i təxirə salmaq
- "I want to flag a risk to the timeline." — proaktiv
- "The estimate is growing because..." — dürüst yeniləmə
- "What's the priority if we run over?" — seçimə məcbur etmək
- "Can we split this into phases?" — hissələrə bölmək
- "I'm building in buffer for unknowns." — padding barədə dürüstlük
- "We found new scope — the estimate changes." — scope böyüməsi
- "I need to re-estimate after this week." — aralıq yoxlama
- "Best case / worst case / likely case." — üç nöqtəli
- "T-shirt size: medium — about 1 week." — sürətli sizing

## Common mistakes / anti-patterns
- Kimisə razı salmaq üçün bir nöqtəli estimate vermək. Həmişə diapazon ver.
- Yenidən estimate etmədən "rough" estimate-i commitment-ə çevirmək.
- Padding yoxdur. Sonra bir günlük qaçırma adət halını alır.
- "Hər ehtimala qarşı" nəhəng padding (3x). Etibarı dağıdır.
- Böyük işi hissələrə bölməmək. Böyük estimatlar həmişə səhvdir.
- Təzyiq hiss etdiyin üçün optimistik estimate etmək. Sonradan bahaya başa gəlir.
- Estimate sürüşəndə xəbər verməmək. Sükut + gec pis xəbər = etibar qatili.
- Spike olmadan heç vaxt etmədiyin işi estimate etmək.
- Estimate-i effort ilə, schedule ilə qarışdırmaq. "2 days of work" iclaslarla dolu bir həftədə həqiqətən bir həftədir.
- PM-lərin mühəndislər üçün estimate etməsinə icazə vermək.

## Interview answer angle
Senior müsahibələrində çıxan ümumi suallar:
- "How do you estimate a large project?"
- "Tell me about a time your estimate was wrong. What happened?"
- "How do you handle an unrealistic deadline?"

Cavab planı:
1. **Çərçivə:** "I distinguish rough estimates from commitments. Early in a project, I give ranges. I only commit after I've scoped the work and broken it down."
2. **Proses:** "I break big tasks into 1-3 day pieces, use PERT for the important ones, and add a 30% buffer for unknowns."
3. **Qaçırılan estimate nümunəsi:** "I once estimated 3 weeks for a data migration. It took 7. I missed the cost of the rollback plan and the DBA dependency. I learned to make 'unknowns lists' explicit in every estimate now. I flagged the slip early — 2 weeks in, not at the deadline."
4. **Qeyri-real deadline-lar:** "I go back with scope options. Never just say yes to something I know is wrong."

## Further reading
- "Software Estimation: Demystifying the Black Art" by Steve McConnell
- "The Mythical Man-Month" by Fred Brooks
- "Agile Estimating and Planning" by Mike Cohn
- "The Phoenix Project" by Gene Kim (contextual, not a manual)
- Martin Fowler's blog: posts on estimation and #NoEstimates debate
