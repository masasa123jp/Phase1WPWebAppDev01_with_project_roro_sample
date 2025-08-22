// models/prefecture.js
// 都道府県マスタのモデル定義
module.exports = (sequelize, DataTypes) => {
  const Prefecture = sequelize.define('Prefecture', {
    prefectureId: {
      type: DataTypes.INTEGER,
      primaryKey: true,
      autoIncrement: true,
      field: 'prefecture_id'
    },
    name: {
      type: DataTypes.STRING(50),
      allowNull: false,
      unique: true,
      comment: '都道府県名'
    }
  }, {
    tableName: 'prefectures',
    timestamps: false
  });
  Prefecture.associate = (models) => {
    // 都道府県は多数の市区町村・観光スポットを持つ
    Prefecture.hasMany(models.City, { foreignKey: 'prefecture_id' });
    Prefecture.hasMany(models.TouristSpot, { foreignKey: 'prefecture_id' });
  };
  return Prefecture;
};
