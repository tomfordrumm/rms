# **Система управления рестораном (Меню + Бронирования)**

Сервис позволяет владельцу ресторана управлять **публичным цифровым меню** и **онлайн-бронированиями столиков**, а гостям — **смотреть меню по QR** и **бронировать стол** без регистрации.


## **Основные сущности**

### **User**

- Fortify по умолчанию

### **Restaurant**

- id
- name
- description
- contacts (nullable, string/json)
- work_hours (string или структурно — см. ниже)
- open_time
- close_time
- closed_dates (JSON)
- logo_path / cover_path (nullable)
- slug (unique)
- timestamps

### Restaurant_User

- restaurant_id
- user_id

### **Table**

- id
- restaurant_id
- name
- capacity
- is_active (bool, default true)
- timestamps

### **Reservation**

- id
- restaurant_id
- table_id
- customer_name
- customer_email
- people_count
- date (date)
- time (time)
- token (unique)
- status (active / cancelled)
- timestamps

### **Category**

- id
- restaurant_id
- name
- description (nullable)
- position (int)
- timestamps

### **Dish**

- id
- restaurant_id
- name
- description
- weight (string)
- price (decimal)
- image_path (nullable)
- is_active (bool, default true)
- timestamps

### **category_dish**

- category_id
- dish_id
