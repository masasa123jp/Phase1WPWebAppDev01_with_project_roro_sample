// models/customerPreference.js
module.exports = (sequelize, DataTypes) => {
  const CustomerPreference = sequelize.define('CustomerPreference', {
    customerId: {
      type: DataTypes.INTEGER,
      primaryKey: true,
      field: 'customer_id'
    },
    categoryId: {
      type: DataTypes.INTEGER,
      primaryKey: true,
      field: 'category_id'
    }
  }, {
    tableName: 'customer_preferences',
    timestamps: false
  });
  return CustomerPreference;
};
