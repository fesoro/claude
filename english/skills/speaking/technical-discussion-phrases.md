# Technical Discussion Phrases

Texniki intervyularda və iş müzakirələrində layihələrinizi, arxitekturanızı və qərarlarınızı İngiliscə izah etmək üçün ifadələr.

---

## 1. Layihəni Təsvir Etmək (Describing a Project)

### Ümumi təsvir
- "The project is a **[type]** that allows users to **[functionality]**."
- "It's basically a **[simple description]** built with **[technologies]**."
- "The main purpose of the application is to **[goal]**."

**Misal:**
> "The project is a web-based task management tool that allows teams to track their work and collaborate in real time. It's built with React on the frontend and Node.js on the backend."

### Texniki detallar
- "The application uses a **[architecture]** architecture."
- "We chose **[technology]** because **[reason]**."
- "The system handles approximately **[number]** requests per day."
- "Data is stored in **[database]** and cached using **[cache system]**."

**Misal:**
> "We use a microservices architecture with five main services. The API gateway handles authentication and routes requests to the appropriate service. Data is stored in PostgreSQL and we use Redis for caching frequently accessed data."

---

## 2. Texniki Qərarları İzah Etmək (Explaining Technical Decisions)

### Niyə bu texnologiyanı seçdiniz?
- "We went with **[tech]** because **[reason]**."
- "The main reason we chose **[tech]** was **[reason]**."
- "We considered **[alternative]** as well, but **[tech]** was a better fit because **[reason]**."
- "**[Tech]** made more sense for our use case because **[reason]**."

**Misal:**
> "We went with PostgreSQL because we needed strong data consistency and complex querying capabilities. We considered MongoDB, but given that our data is highly relational, a SQL database was a better fit."

### Trade-off izah etmək
- "The trade-off was between **[A]** and **[B]**."
- "We prioritised **[A]** over **[B]** because **[reason]**."
- "The downside of this approach is **[downside]**, but the benefit is **[benefit]**."

**Misal:**
> "The trade-off was between development speed and scalability. We prioritised getting the product to market quickly, so we started with a monolithic architecture. We plan to migrate to microservices as we scale."

---

## 3. Arxitekturanı İzah Etmək (Explaining Architecture)

### Komponentləri təsvir etmək
- "The system consists of **[number]** main components: **[list]**."
- "The **[component]** is responsible for **[function]**."
- "**[Component A]** communicates with **[Component B]** via **[method]**."
- "The data flows from **[source]** through **[process]** to **[destination]**."

**Misal:**
> "The system consists of three main components: the API server, the background job processor, and the notification service. The API server handles all user requests. When a user creates an order, it sends a message to the job processor via a Redis queue. The job processor validates the payment and then triggers the notification service to send a confirmation email."

### Miqyaslama haqqında danışmaq
- "The system is designed to scale **horizontally / vertically**."
- "We use **[tool]** for load balancing."
- "The database is **replicated / sharded** to handle **[load]**."

---

## 4. Problemləri və Həlləri İzah Etmək (Discussing Problems & Solutions)

### Problemi təsvir etmək
- "We ran into an issue with **[area]**."
- "The main challenge was **[challenge]**."
- "We noticed that **[symptom]** was happening because **[cause]**."
- "One of the bottlenecks was **[bottleneck]**."

### Həlli təsvir etmək
- "To solve this, we **[action]**."
- "The approach we took was to **[action]**."
- "We resolved it by **[action]**, which reduced **[metric]** by **[amount]**."
- "After investigating, we found that **[root cause]**, so we **[fix]**."

**Misal:**
> "We ran into a performance issue where API response times were averaging eight seconds during peak hours. After investigating, we found that the database queries weren't using indexes efficiently. We added composite indexes and implemented query caching. Response times dropped to under one second."

---

## 5. Kodunuz Haqqında Danışmaq (Talking About Your Code)

### Yanaşmanızı izah etmək
- "I structured the code using **[pattern]**."
- "I followed the **[principle]** principle to keep the code **[quality]**."
- "The function takes **[input]** and returns **[output]**."
- "I used **[design pattern]** here because **[reason]**."

### Kod nəzərdən keçirmə
- "I'd suggest refactoring this part to **[improvement]**."
- "This could be improved by **[suggestion]**."
- "The reason I wrote it this way is **[reason]**."
- "Looking at it now, I would probably change **[part]** to **[improvement]**."

---

## 6. Bilmədiyiniz Zaman (When You Don't Know)

Bu ifadələr intervyuda bilmədiyinizi peşəkar şəkildə ifadə etmək üçündür:

- "I haven't worked with **[tech]** directly, but I'm familiar with the concepts."
- "That's not something I've had hands-on experience with, but I'd approach it by **[approach]**."
- "I'm not sure about the specifics, but based on my understanding of **[related topic]**, I would say **[educated guess]**."
- "I'd need to research that further, but my initial thought is **[thought]**."
- "Honestly, I don't have experience with that, but I'm a quick learner and I'm confident I could pick it up."

---

## 7. Sual Soruşmaq (Asking Clarifying Questions)

Texniki müzakirədə sual soruşmaq zəiflik deyil — gücdür:

- "Just to clarify — when you say **[term]**, do you mean **[interpretation]**?"
- "Could you give me a bit more context about **[topic]**?"
- "Are there any specific constraints I should consider?"
- "What scale are we talking about — hundreds of users or millions?"
- "Is there a preference for **[option A]** versus **[option B]**?"

---

## Məşq: Öz Layihənizi Təsvir Edin

Aşağıdakı suallara İngiliscə cavab yazın (hər biri 3-5 cümlə):

1. **What is your most recent project about?**
   Your answer: _______________________________________

2. **What technologies did you use and why?**
   Your answer: _______________________________________

3. **What was the biggest technical challenge you faced?**
   Your answer: _______________________________________

4. **How did you solve it?**
   Your answer: _______________________________________

5. **If you could start the project over, what would you do differently?**
   Your answer: _______________________________________

Sonra hər cavabı 2 dəqiqədən az vaxtda səsli deyin.
