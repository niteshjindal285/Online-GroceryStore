const mongoose = require('mongoose');
const Company = require('../models/Company');
const TaxClass = require('../models/TaxClass');
const User = require('../models/User'); // Assuming basic users exist

mongoose.connect(process.env.MONGO_URI);

const seedData = async () => {
  try {
    // Sample companies (from ERP multi-company)
    await Company.create([
      { name: 'Store To Door Co', code: 'STD001', managerId: null } // Link to admin later
    ]);

    // Sample tax classes
    await TaxClass.create([
      { name: 'Standard Sales Tax', rate: 0.05, type: 'sales' },
      { name: 'GST Purchase', rate: 0.18, type: 'purchase' }
    ]);

    console.log('ERP seed data inserted');
    process.exit(0);
  } catch (err) {
    console.error('Seed error:', err);
    process.exit(1);
  }
};

seedData();

