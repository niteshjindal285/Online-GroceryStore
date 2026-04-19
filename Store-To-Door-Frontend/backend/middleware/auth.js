const jwt = require('jsonwebtoken');
const User = require('../models/User');
const dotenv = require('dotenv');
dotenv.config();

const auth = async (req, res, next) => {
  const token = req.header('Authorization') && req.header('Authorization').replace('Bearer ', '');
  if (!token) return res.status(401).json({ message: 'No token, authorization denied' });
  try {
    const decoded = jwt.verify(token, process.env.JWT_SECRET || 'your_jwt_secret_here');
    const user = await User.findById(decoded.id).select('-password');
    
    if (!user) {
      console.warn(`Auth failed: User ${decoded.id} not found in database`);
      return res.status(401).json({ message: 'User no longer exists' });
    }
    
    req.user = user;
    next();
  } catch (err) {
    console.error('JWT Verification Error:', err.message);
    res.status(401).json({ message: 'Token is not valid' });
  }
};

module.exports = auth;
