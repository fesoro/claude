# Live Coding Narration

Texniki müsahibədə live coding — yalnız kod yazmaq deyil, **düşüncəni səsə çevirmək**. Bu fərq junior ilə senior arasındakı ən görünən fərqlərdən biridir.

---

## 1. Niyə lazımdır?

Müsahibəçi yalnız həllinə baxmır — **düşüncə prosesinə** baxır:
- Problemi necə parçalayırsın?
- Suallar verirsən?
- Trade-off-ları görmüsən?
- Çıxılmaz vəziyyətdə nə edirsən?

Susub yazan developer narahat görünür. Danışaraq yazan developer inamlı görünür.

---

## 2. Müsahibənin əvvəlindən başla

### Problemi başa düşdüyünü göstər:
```
"Before I start coding, let me make sure I understand the problem correctly."
"So we need to [restate the problem in your own words] — is that right?"
"Are there any constraints I should be aware of? Time complexity, memory?"
"Can I assume the input is always valid, or do I need to handle edge cases?"
"What scale are we working with — small input or potentially millions of records?"
```

### Yanaşmanı əvvəlcə aydınlaşdır:
```
"I'm thinking of using [approach]. The reason is [X]."
"My first instinct is a [hash map / sliding window / BFS], because..."
"Before I write any code, let me sketch the high-level approach."
"Is it okay if I start with a brute-force solution and then optimize?"
```

---

## 3. Kod yazarkən danış

### Nə etdiyini izah et:
```
"I'm going to create a helper function here to keep things clean."
"I'll use a map to store [X] — this gives us O(1) lookup."
"I'm iterating through the array here to [reason]."
"I'll handle the edge case first — empty input."
```

### Düşündüğünü göstər:
```
"Let me think about this for a second..."
"So if the input is [X], then we'd need to..."
"Actually, wait — I think I should approach this differently."
"I realize I need to handle [case] here, otherwise it would break."
```

### Trade-off-ları ifadə et:
```
"This approach is O(n log n), but if time isn't a constraint, 
it's simpler to reason about."
"I could use recursion here, but it risks stack overflow on large inputs. 
An iterative approach is safer."
"This is more verbose, but much more readable. Want me to condense it?"
```

---

## 4. Çıxılmaz vəziyyətdə

Ən pis ssenari: susursun. Bunu əvəzinə de:

```
"I'm not immediately sure about this part — let me think through it aloud."
"I know the end result should be [X], but I need to figure out the middle step."
"I'm going to write a placeholder here and come back to it."
"I'm getting a bit stuck — could I get a small hint on [specific part]?"
```

### Bilib-bilmədiyini göstər:
```
"I know this library has a method for this, but I don't remember 
the exact signature — do you mind if I pseudo-code it for now?"
"In production I'd look this up, but my understanding is [X]."
```

---

## 5. Bitirdikdən sonra

Həmişə kodu test et — hətta şifahi olaraq:

```
"Let me trace through this with a simple example to verify."
"If the input is [1, 2, 3], the output should be [X]. Let me trace through..."
"The edge cases I'm thinking of: empty array, single element, all duplicates."
"I think the time complexity here is O(n) because [reason]."
"Space complexity is O(n) for the hash map."
```

### Optimizasiya soruşmaq:
```
"I'm happy with this solution, but would you like me to optimize it?"
"I see a potential improvement here — want me to refactor?"
"This works correctly, but I notice [potential issue]. Should I address that?"
```

---

## 6. System design müsahibəsindəki fərqlər

Live coding ilə yanaşı, system design müsahibəsindəki danışıq da fərqlidir:

```
"Before I dive in, a few clarifying questions:
- What scale are we designing for? DAU?
- Is this read-heavy or write-heavy?
- Are there any latency requirements — SLA targets?
- Do we need multi-region support?"
```

```
"I'll start with the high-level components and then drill into the 
parts you're most interested in."
"The bottleneck here would be [X]. To address that, I'd use [Y]."
"This is a trade-off between [consistency] and [availability] — 
in this case I'd prioritise [X] because [reason]."
```

---

## 7. Tez başvurma: frazalar qruplaşdırılmış

### Başlanğıc:
- "Let me make sure I understand..."
- "Before I start, can I ask..."
- "Is it okay if I think out loud?"

### Kod yazarkən:
- "I'm using X here because..."
- "This handles the edge case where..."
- "Let me simplify this..."

### Düşünərkən:
- "Give me a moment to think..."
- "Actually, I think a better approach would be..."
- "Wait — if I do this, then that would break..."

### Test edərkən:
- "Let me trace through with an example..."
- "Edge case: what if the input is empty?"
- "The time complexity here is O(n) because..."

### Yardım istəyərkən:
- "I'm a bit stuck — could you give me a small hint?"
- "I know the concept but I'm blanking on the syntax..."

---

## Əlaqəli Mövzular
- `skills/speaking/system-design-discussion.md`
- `skills/speaking/relocation-job-interview.md`
- `skills/speaking/job-interview.md`
