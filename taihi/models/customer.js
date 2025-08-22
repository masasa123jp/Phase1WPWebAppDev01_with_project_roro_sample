// models/customer.js
module.exports = (sequelize, DataTypes) => {
  const Customer = sequelize.define('Customer', {
    customerId: {
      type: DataTypes.INTEGER,
      primaryKey: true,
      autoIncrement: true,
      field: 'customer_id'
    },
    name: {
      type: DataTypes.STRING(100),
      allowNull: false,
      comment: '氏名'
    },
    kana: {
      type: DataTypes.STRING(100),
      allowNull: false,
      comment: 'ふりがな',
      index: true
    },
    birthday: {
      type: DataTypes.DATEONLY,
      allowNull: true,
      comment: '生年月日'
    },
    email: {
      type: DataTypes.STRING(200),
      allowNull: true,
      unique: true,
      validate: { isEmail: true }
    },
    phone: {
      type: DataTypes.STRING(20),
      allowNull: true
    },
    zipCode: {
      type: DataTypes.STRING(10),
      allowNull: true,
      field: 'zip_code'
    },
    prefectureId: {
      type: DataTypes.INTEGER,
      allowNull: true,
      field: 'prefecture_id'
    },
    cityId: {
      type: DataTypes.INTEGER,
      allowNull: true,
      field: 'city_id'
    },
    address2: {
      type: DataTypes.STRING(200),
      allowNull: true,
      comment: '番地以下'
    },
    remarks: {
      type: DataTypes.TEXT,
      allowNull: true,
      comment: '備考'
    }
  }, {
    tableName: 'customers',
    indexes: [
      { fields: ['kana'] },
      { fields: ['email'], unique: true }
    ]
  });
  Customer.associate = (models) => {
    Customer.belongsTo(models.Prefecture, { foreignKey: 'prefecture_id' });
    Customer.belongsTo(models.City, { foreignKey: 'city_id' });
    Customer.belongsToMany(models.SpotCategory, {
      through: models.CustomerPreference,
      foreignKey: 'customer_id',
      otherKey: 'category_id'
    });
    Customer.belongsToMany(models.TouristSpot, {
      through: models.FavoriteSpot,
      foreignKey: 'customer_id',
      otherKey: 'spot_id'
    });
  };
  return Customer;
};
