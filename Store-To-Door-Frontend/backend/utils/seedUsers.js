const mongoose = require('mongoose');
const bcrypt = require('bcryptjs');
const dotenv = require('dotenv');
const User = require('../models/User');

dotenv.config();

const MONGO_URI = process.env.MONGO_URI || 'mongodb://localhost:27017/online-grocery-store';

mongoose.connect(MONGO_URI, { useNewUrlParser: true, useUnifiedTopology: true })
    .then(async () => {
        console.log('MongoDB connected for seeding demo users...');

        // Seed Admin
        const adminExists = await User.findOne({ email: 'admin@test.com' });
        if (!adminExists) {
            const salt = await bcrypt.genSalt(10);
            const hashedPassword = await bcrypt.hash('password', salt);
            await User.create({
                name: 'Admin User',
                email: 'admin@test.com',
                password: hashedPassword,
                isAdmin: true
            });
            console.log('✅ Admin user created (admin@test.com / password)');
        } else {
            console.log('⚡ Admin user already exists');
        }

        // Seed Customer
        const customerExists = await User.findOne({ email: 'customer@test.com' });
        if (!customerExists) {
            const salt = await bcrypt.genSalt(10);
            const hashedPassword = await bcrypt.hash('password', salt);
            await User.create({
                name: 'Demo Customer',
                email: 'customer@test.com',
                password: hashedPassword,
                isAdmin: false
            });
            console.log('✅ Customer user created (customer@test.com / password)');
        } else {
            console.log('⚡ Customer user already exists');
        }

        // Seed Vendor
        const vendorExists = await User.findOne({ email: 'vendor@test.com' });
        if (!vendorExists) {
            const salt = await bcrypt.genSalt(10);
            const hashedPassword = await bcrypt.hash('password', salt);
            await User.create({
                name: 'Demo Vendor',
                email: 'vendor@test.com',
                password: hashedPassword,
                isAdmin: false
            });
            console.log('✅ Vendor user created (vendor@test.com / password)');
        } else {
            console.log('⚡ Vendor user already exists');
        }

        console.log('🎉 Demo accounts successfully seeded into MongoDB! You can now log in.');
        process.exit(0);
    })
    .catch(err => {
        console.error('MongoDB connection error:', err);
        process.exit(1);
    });
