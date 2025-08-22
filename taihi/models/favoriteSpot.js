// models/favoriteSpot.js
module.exports = (sequelize, DataTypes) => {
  const FavoriteSpot = sequelize.define('FavoriteSpot', {
    customerId: {
      type: DataTypes.INTEGER,
      primaryKey: true,
      field: 'customer_id'
    },
    spotId: {
      type: DataTypes.INTEGER,
      primaryKey: true,
      field: 'spot_id'
    },
    registeredAt: {
      type: DataTypes.DATE,
      allowNull: false,
      defaultValue: DataTypes.NOW,
      field: 'registered_at'
    }
  }, {
    tableName: 'favorite_spots',
    timestamps: false
  });
  return FavoriteSpot;
};
