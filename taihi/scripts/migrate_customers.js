// scripts/migrate_customers.js
// このスクリプトは旧customersテーブルのデータを新customersテーブルに移行します。
// 旧テーブル構造: id, name, kana, birthday, zip_code, prefecture_name, city_name, address, phone, email, remarks
// 新テーブル構造: customers (customer_id, name, kana, birthday, email, phone, zip_code,
//                   prefecture_id, city_id, address2, remarks)
//
// 実行方法: node scripts/migrate_customers.js

const { Sequelize, Op } = require('sequelize');
const sequelize = new Sequelize(process.env.DATABASE_URL, {
  dialect: 'mysql',
  logging: console.log
});

async function migrate() {
  // 一時的な旧テーブル定義
  const OldCustomer = sequelize.define('OldCustomer', {
    id: { type: Sequelize.INTEGER, primaryKey: true },
    name: Sequelize.STRING,
    kana: Sequelize.STRING,
    birthday: Sequelize.DATEONLY,
    zip_code: Sequelize.STRING,
    prefecture_name: Sequelize.STRING,
    city_name: Sequelize.STRING,
    address: Sequelize.STRING,
    phone: Sequelize.STRING,
    email: Sequelize.STRING,
    remarks: Sequelize.TEXT
  }, {
    tableName: 'customers_old',
    timestamps: false
  });

  // 新テーブルのモデル
  const Prefecture = sequelize.define('Prefecture', {
    prefectureId: { type: Sequelize.INTEGER, primaryKey: true, field: 'prefecture_id' },
    name: Sequelize.STRING
  }, { tableName: 'prefectures', timestamps: false });

  const City = sequelize.define('City', {
    cityId: { type: Sequelize.INTEGER, primaryKey: true, field: 'city_id' },
    prefectureId: { type: Sequelize.INTEGER, field: 'prefecture_id' },
    name: Sequelize.STRING
  }, { tableName: 'cities', timestamps: false });

  const Customer = sequelize.define('Customer', {
    customerId: { type: Sequelize.INTEGER, primaryKey: true, autoIncrement: true, field: 'customer_id' },
    name: Sequelize.STRING,
    kana: Sequelize.STRING,
    birthday: Sequelize.DATEONLY,
    email: Sequelize.STRING,
    phone: Sequelize.STRING,
    zipCode: { type: Sequelize.STRING, field: 'zip_code' },
    prefectureId: { type: Sequelize.INTEGER, field: 'prefecture_id' },
    cityId: { type: Sequelize.INTEGER, field: 'city_id' },
    address2: Sequelize.STRING,
    remarks: Sequelize.TEXT
  }, {
    tableName: 'customers',
    timestamps: false
  });

  await sequelize.authenticate();
  const oldData = await OldCustomer.findAll();

  for (const old of oldData) {
    // 都道府県名からマスタIDを取得
    const pref = await Prefecture.findOne({ where: { name: old.prefecture_name } });
    // 市区町村名からマスタIDを取得。都道府県を指定して検索しないと同名市区町村が複数存在する場合に誤ってしまう。
    const city = await City.findOne({
      where: {
        name: old.city_name,
        ...(pref && { prefectureId: pref.prefectureId })
      }
    });

    await Customer.create({
      name: old.name,
      kana: old.kana,
      birthday: old.birthday,
      email: old.email,
      phone: old.phone,
      zipCode: old.zip_code,
      prefectureId: pref ? pref.prefectureId : null,
      cityId: city ? city.cityId : null,
      address2: old.address,
      remarks: old.remarks
    });
  }
  console.log('Migration finished.');
}

migrate().catch((err) => {
  console.error(err);
  process.exit(1);
});
