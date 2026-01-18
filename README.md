# CSD Canteen Management System

A web-based **Canteen Management System** developed using **PHP and MySQL** to digitalize canteen operations such as product management, ordering, cart handling, returns, and user administration. This project is designed for academic use and is suitable for **BCA final-year / mini projects**.

---

## 📌 Project Overview

The **CSD Canteen Management System** provides an online platform where users can browse canteen products, place orders, manage carts and addresses, and request order returns. An admin panel is included to manage products, stock, orders, and return approvals.

---

## 🛠️ Technology Stack

- **Frontend:** HTML, CSS, JavaScript  
- **Backend:** PHP  
- **Database:** MySQL  
- **Server:** Apache (XAMPP / WAMP / LAMP)

---

## 👥 User Roles

### 🔹 User (Customer)
- Register and login
- View available products
- Add/remove items from cart
- Manage delivery addresses
- Place and cancel orders
- Request product returns
- Track order and return status
- Update profile
- Contact admin

### 🔹 Admin
- Admin login
- Add, update, and delete products
- Manage stock
- View and update order status
- Approve or reject return requests
- Manage returned stock

---

## 📂 Project Structure

Army-CSD-Canteen/
CSD_Canteen1/
|
├── add_address.php
├── add_product.php
├── add_review.php
├── add_to_cart.php
├── admin_panel.php
├── adminlog.php
├── approve_return.php
├── cancel.php
├── cancel_order.php
├── cart.php
├── checkout.php
├── config.php  # Database configuration
├── contact.php
├── db/
│   └── (database-related files)
├── delete_address.php
├── delete_product.php
├── edit_address.php
├── edit_product.php
├── footer.php
├── header.php
├── index.php
├── login.php
├── logout.php
├── manage_orders.php
├── manage_products.php
├── my_orders.php
├── payment.php
├── place_order.php
├── product_details.php
├── register.php
├── return_order.php
├── search.php
├── update_cart.php
├── update_order_status.php
├── update_profile.php
├── update_stock.php
├── wait_approval.php
└── uploads/  # Product images




---

## 🗄️ Database (MySQL)

The database includes tables such as:
- `users`
- `admins`
- `products`
- `cart`
- `orders`
- `order_items`
- `addresses`
- `returns`
- `messages`

Supports relational data with primary and foreign keys.

---

## ⚙️ Installation & Setup

1. Install **XAMPP**
2. Clone or download this repository
3. Copy the project folder to:
4. Start **Apache** and **MySQL** from XAMPP Control Panel
5. Import the database:
- Open `phpMyAdmin`
- Create a new database
- Import SQL file from the `db/` folder
6. Configure database credentials in `config.php`
7. Run the project in browser:
  http://localhost/Army-CSD-Canteen


  

---

## 🔐 Features

- Session-based authentication
- Role-based access (Admin / User)
- CRUD operations
- Image upload handling
- Stock management
- Order and return workflow
- Secure database connectivity

---

## 🎓 Academic Relevance

- Demonstrates **full-stack web development**
- Implements **real-world e-commerce logic**
- Covers **DBMS, PHP, Software Engineering concepts**
- Ideal for **BCA students**

---

## 🚀 Future Enhancements

- Online payment integration
- Email/SMS notifications
- Improved UI using Bootstrap/React
- Role-based dashboards
- Enhanced security (password hashing, validation)

---

## 📄 License

This project is for **educational purposes only**.

---

## 🙌 Author

**Abhinav B**  
BCA Student  
