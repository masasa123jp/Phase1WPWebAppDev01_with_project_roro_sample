// models/city.js
module.exports = (sequelize, DataTypes) => {
  const City = sequelize.define('City', {
    cityId: {
      type: DataTypes.INTEGER,
      primaryKey: true,
      autoIncrement: true,
      field: 'city_id'
    },
    prefectureId: {
      type: DataTypes.INTEGER,
      allowNull: false,
      field: 'prefecture_id',
      references: { model: 'prefectures', key: 'prefecture_id' }
    },
    name: {
      type: DataTypes.STRING(100),
      allowNull: false,
      comment: '市区町村名'
    }
  }, {
    tableName: 'cities',
    timestamps: false
  });
  City.associate = (models) => {
    City.belongsTo(models.Prefecture, { foreignKey: 'prefecture_id' });
    City.hasMany(models.TouristSpot, { foreignKey: 'city_id' });
  };
  return City;
};
