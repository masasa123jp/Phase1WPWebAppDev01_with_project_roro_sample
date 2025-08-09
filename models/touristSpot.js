// models/touristSpot.js
module.exports = (sequelize, DataTypes) => {
  const TouristSpot = sequelize.define('TouristSpot', {
    spotId: {
      type: DataTypes.INTEGER,
      primaryKey: true,
      autoIncrement: true,
      field: 'spot_id'
    },
    name: {
      type: DataTypes.STRING(200),
      allowNull: false,
      comment: 'スポット名'
    },
    prefectureId: {
      type: DataTypes.INTEGER,
      allowNull: false,
      field: 'prefecture_id'
    },
    cityId: {
      type: DataTypes.INTEGER,
      allowNull: false,
      field: 'city_id'
    },
    categoryId: {
      type: DataTypes.INTEGER,
      allowNull: false,
      field: 'category_id'
    },
    description: {
      type: DataTypes.TEXT,
      allowNull: true,
      comment: '説明文'
    },
    imageUrl: {
      type: DataTypes.STRING(500),
      allowNull: true,
      field: 'image_url'
    },
    address: {
      type: DataTypes.STRING(300),
      allowNull: true
    },
    priceRange: {
      type: DataTypes.STRING(50),
      allowNull: true,
      field: 'price_range'
    },
    rating: {
      type: DataTypes.DECIMAL(2, 1),
      allowNull: true,
      comment: '評価点(5段階)'
    }
  }, {
    tableName: 'tourist_spots',
    indexes: [
      { fields: ['name'] },
      { fields: ['prefecture_id', 'category_id'] }
    ]
  });
  TouristSpot.associate = (models) => {
    TouristSpot.belongsTo(models.Prefecture, { foreignKey: 'prefecture_id' });
    TouristSpot.belongsTo(models.City, { foreignKey: 'city_id' });
    TouristSpot.belongsTo(models.SpotCategory, { foreignKey: 'category_id' });
    TouristSpot.belongsToMany(models.Customer, {
      through: models.FavoriteSpot,
      foreignKey: 'spot_id',
      otherKey: 'customer_id'
    });
  };
  return TouristSpot;
};
