CREATE DATABASE HUSTLEBOOK;
USE HUSTLEBOOK;
CREATE TABLE PRODUCTS (
    product_id INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
    product_name VARCHAR(255),
    stocking_price DECIMAL(10, 2),
    selling_price DECIMAL(10, 2),
    stock_quantity INT
    
)

CREATE TABLE CUSTOMERS (
    customer_id INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
    customer_name VARCHAR(255),
    phone INT,
    gender ENUM('Male', 'Female'),
    location VARCHAR(255),
    address VARCHAR(255)
)

CREATE TABLE SOURCES (
    source_id INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
    source_name VARCHAR(255),
    source_type ENUM('DM', 'Phone', 'Direct')
)

CREATE TABLE INTERACTIONS (
    interaction_id INT AUTO_INCREMENT NOT NULL,
    customer_id INT,
    source_id INT,
    interaction_direction ENUM('Inbound', 'Outbound'),
    inbox_datetime DATETIME,
    response_datetime DATETIME,
    delivery_datetime DATETIME,
    is_sale BOOLEAN,
    sale_quantity INT,
    free_gift BOOLEAN,
    
    comment TEXT,
    PRIMARY KEY (interaction_id),
    FOREIGN KEY (customer_id) REFERENCES CUSTOMERS(customer_id),
    FOREIGN KEY (source_id) REFERENCES SOURCES(source_id)

)



CREATE TABLE STOCK_MOVEMENT (
    movement_id INT AUTO_INCREMENT NOT NULL,
    product_id INT,
    quantity INT,
    movement_type ENUM('IN', 'OUT'),
    movement_date DATETIME,
    comments TEXT,
    PRIMARY KEY (movement_id),
    FOREIGN KEY (product_id) REFERENCES PRODUCTS(product_id)
    
)