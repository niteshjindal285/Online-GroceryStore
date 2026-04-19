# Store-To-Door Backend

Node.js + Express backend for the Store-To-Door frontend.

## Features
- MongoDB (Mongoose) models for Users, Products, Cart, Orders
- JWT Authentication (register/login)
- Product upload (multer) and static serving from `/uploads`
- Seed script to insert sample products and images

## Quick start
1. Copy `.env.example` to `.env` and set `MONGO_URI` and `JWT_SECRET`.
2. Install dependencies:
   ```
   npm install
   ```
3. (Optional) Seed sample products:
   ```
   npm run seed
   ```
4. Start server:
   ```
   npm run dev
   ```
5. API endpoints:
   - `POST /api/users/register`
   - `POST /api/users/login`
   - `GET /api/products`
   - `GET /api/products/:id`
   - `POST /api/products` (multipart/form-data, admin)
   - `GET /api/cart` (auth)
   - `POST /api/cart` (auth)
   - `POST /api/orders` (auth)

## Notes
- Static product images are placed in `/uploads/products/`.
- Designed to work with typical React frontends; adjust routes if your frontend expects different paths.
