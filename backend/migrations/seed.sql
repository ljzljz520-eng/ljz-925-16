-- 卡密访问控制系统种子数据
-- UTF-8 编码

-- 插入默认管理员账号
-- 用户名: admin
-- 密码: daniaoge
-- 密码哈希使用 PHP password_hash() 生成
INSERT INTO admin_user (username, password_hash, created_at, updated_at) VALUES
('admin', '$2y$12$eiFGuGoCPn3cvq4PJueC9OGMNethHejulHKyHLBdhFEBkXpKtzDWu', datetime('now', 'localtime'), datetime('now', 'localtime'));

-- 插入系统配置
INSERT INTO system_config (key, value, updated_at) VALUES
('server_secret', 'test-secret-key-for-development-only', datetime('now', 'localtime')),
('token_expire_hours', '12', datetime('now', 'localtime')),
('max_attempts_per_ip', '10', datetime('now', 'localtime')),
('rate_limit_window_seconds', '300', datetime('now', 'localtime'));

-- 插入问题类型（10种常见问题）
INSERT INTO problem_types (name, description, sort_order, created_at) VALUES
('虚假宣传', '商家夸大宣传、虚假广告、货不对板等问题', 1, datetime('now', 'localtime')),
('质量问题', '商品质量不合格、存在瑕疵、假冒伪劣等', 2, datetime('now', 'localtime')),
('售后纠纷', '退换货困难、售后服务态度差、不履行承诺等', 3, datetime('now', 'localtime')),
('价格欺诈', '虚标原价、价格误导、不明码标价等', 4, datetime('now', 'localtime')),
('物流问题', '发货延迟、物流损坏、快递丢失等', 5, datetime('now', 'localtime')),
('合同违约', '商家单方面取消订单、不按约定发货等', 6, datetime('now', 'localtime')),
('个人信息泄露', '未经同意收集个人信息、信息泄露等', 7, datetime('now', 'localtime')),
('强制消费', '捆绑销售、强制搭售、诱导消费等', 8, datetime('now', 'localtime')),
('霸王条款', '不公平格式条款、免除商家责任等', 9, datetime('now', 'localtime')),
('食品安全', '食品过期、卫生不达标、标签不符等', 10, datetime('now', 'localtime'));

-- 插入模板数据（每种类型5个模板）

-- 虚假宣传模板
INSERT INTO templates (problem_type_id, template_type, title, content, created_at) VALUES
(1, 'SLOGAN', '虚假宣传基础话术', '您好，我在贵店购买的商品与宣传描述严重不符，涉嫌虚假宣传。根据《消费者权益保护法》第二十条规定，经营者提供商品或者服务应当明码标价。我要求退货退款并赔偿损失。', datetime('now', 'localtime')),
(1, 'SLOGAN', '虚假宣传强硬话术', '贵店商品宣传与实物严重不符，已构成欺诈。根据《消费者权益保护法》第五十五条，我有权要求三倍赔偿。请立即处理，否则将向市场监管部门投诉并保留法律追诉权。', datetime('now', 'localtime')),
(1, 'SLOGAN', '虚假宣传协商话术', '您好，商品收到后发现与页面描述差距较大，感觉被误导了。希望能协商解决，退货退款即可。如果处理不当，我会考虑向消协投诉。', datetime('now', 'localtime')),
(1, 'SLOGAN', '虚假宣传证据话术', '我已保存完整的商品宣传截图、聊天记录和实物照片作为证据。贵店的宣传明显夸大其词，涉嫌虚假广告。请在24小时内给出解决方案，否则将提交相关部门处理。', datetime('now', 'localtime')),
(1, 'SLOGAN', '虚假宣传法律话术', '根据《广告法》第二十八条，虚假广告需承担相应法律责任。贵店的宣传已构成虚假广告，我要求退一赔三。请重视此事，避免事态扩大。', datetime('now', 'localtime'));

-- 质量问题模板
INSERT INTO templates (problem_type_id, template_type, title, content, created_at) VALUES
(2, 'SLOGAN', '质量问题基础话术', '您好,收到的商品存在明显质量问题,无法正常使用。根据《产品质量法》,我有权要求退货退款。请尽快处理,谢谢。', datetime('now', 'localtime')),
(2, 'SLOGAN', '质量问题严重话术', '商品质量严重不合格,已影响正常使用甚至存在安全隐患。根据法律规定,我要求退货退款并赔偿相关损失。如不及时处理,将向质监部门举报。', datetime('now', 'localtime')),
(2, 'SLOGAN', '质量问题鉴定话术', '商品存在质量问题,我已联系第三方机构进行质量鉴定。如鉴定结果证实质量不合格,贵店需承担鉴定费用并进行赔偿。请配合处理。', datetime('now', 'localtime')),
(2, 'SLOGAN', '质量问题三包话术', '根据三包规定,商品在保修期内出现质量问题应免费维修或更换。现要求贵店履行三包义务,否则将投诉至消协和市场监管部门。', datetime('now', 'localtime')),
(2, 'SLOGAN', '质量问题假冒话术', '收到的商品疑似假冒伪劣产品,与正品存在明显差异。我已向品牌方求证,如确认为假货,将向工商部门举报并要求十倍赔偿。', datetime('now', 'localtime'));

-- 售后纠纷模板
INSERT INTO templates (problem_type_id, template_type, title, content, created_at) VALUES
(3, 'SLOGAN', '售后纠纷基础话术', '您好,我申请退货已多日,但一直未得到处理。根据《消费者权益保护法》,我有权在七日内无理由退货。请尽快处理退货申请。', datetime('now', 'localtime')),
(3, 'SLOGAN', '售后纠纷拖延话术', '贵店售后一直拖延处理,严重侵犯消费者权益。我已多次沟通无果,现要求立即退款,否则将向电商平台投诉并申请介入。', datetime('now', 'localtime')),
(3, 'SLOGAN', '售后纠纷态度话术', '贵店客服态度恶劣,推诿责任,拒不解决问题。这种服务态度严重违反商业道德。我要求更换客服并立即处理我的售后申请。', datetime('now', 'localtime')),
(3, 'SLOGAN', '售后纠纷承诺话术', '购买时贵店承诺提供完善售后服务,但现在出现问题却拒不履行。这属于虚假承诺,我要求按照承诺处理并赔偿我的时间损失。', datetime('now', 'localtime')),
(3, 'SLOGAN', '售后纠纷平台话术', '贵店拒绝处理合理售后要求,我已向平台客服反映情况。如继续拖延,将申请平台介入并在评价中如实反映售后问题。', datetime('now', 'localtime'));

-- 价格欺诈模板
INSERT INTO templates (problem_type_id, template_type, title, content, created_at) VALUES
(4, 'SLOGAN', '价格欺诈基础话术', '您好,贵店商品存在价格欺诈行为,虚标原价误导消费者。根据《价格法》,我要求退款并赔偿。请立即处理。', datetime('now', 'localtime')),
(4, 'SLOGAN', '价格欺诈虚标话术', '贵店标注的原价从未实际销售过,属于虚构原价欺骗消费者。我已截图保存证据,要求按照《价格法》进行处罚并赔偿。', datetime('now', 'localtime')),
(4, 'SLOGAN', '价格欺诈误导话术', '商品页面价格标注混乱,实际支付金额与显示价格不符,存在价格误导。要求退还多收费用并给予合理解释。', datetime('now', 'localtime')),
(4, 'SLOGAN', '价格欺诈促销话术', '贵店促销活动存在价格欺诈,先提价再打折,实际价格并未优惠。这种行为违反《反不正当竞争法》,要求退款并赔偿。', datetime('now', 'localtime')),
(4, 'SLOGAN', '价格欺诈举报话术', '贵店的价格欺诈行为已严重侵害消费者权益,我将向价格监督部门举报。请在收到此消息后24小时内给出解决方案。', datetime('now', 'localtime'));

-- 物流问题模板
INSERT INTO templates (problem_type_id, template_type, title, content, created_at) VALUES
(5, 'SLOGAN', '物流问题延迟话术', '您好,订单已超过承诺发货时间仍未发货,严重影响我的使用计划。要求立即发货或取消订单全额退款。', datetime('now', 'localtime')),
(5, 'SLOGAN', '物流问题损坏话术', '收到的商品在运输过程中损坏,包装破损严重。虽是物流问题,但贵店作为发货方有责任确保商品完好送达。要求重新发货或退款。', datetime('now', 'localtime')),
(5, 'SLOGAN', '物流问题丢失话术', '快递显示已签收但我未收到商品,疑似快递丢失。贵店作为发货方应承担责任,要求重新发货或全额退款。', datetime('now', 'localtime')),
(5, 'SLOGAN', '物流问题虚假话术', '订单显示已发货但物流信息长期不更新,怀疑是虚假发货。要求提供真实物流信息或立即退款。', datetime('now', 'localtime')),
(5, 'SLOGAN', '物流问题拒收话术', '由于贵店长期不发货,我已不需要该商品。现拒收快递并要求全额退款,运费由贵店承担。', datetime('now', 'localtime'));

-- 合同违约模板
INSERT INTO templates (problem_type_id, template_type, title, content, created_at) VALUES
(6, 'SLOGAN', '合同违约取消话术', '贵店在我付款后单方面取消订单,严重违反合同约定。要求按照约定履行合同或赔偿我的损失。', datetime('now', 'localtime')),
(6, 'SLOGAN', '合同违约缺货话术', '下单时显示有货,付款后却告知缺货无法发货。这属于合同违约,要求赔偿我的时间成本和差价损失。', datetime('now', 'localtime')),
(6, 'SLOGAN', '合同违约赠品话术', '购买时承诺的赠品未随货发出,这违反了购买合同。要求补发赠品或按赠品价值退款。', datetime('now', 'localtime')),
(6, 'SLOGAN', '合同违约服务话术', '贵店承诺提供的服务未兑现,属于合同违约。要求履行服务承诺或解除合同并退款。', datetime('now', 'localtime')),
(6, 'SLOGAN', '合同违约规格话术', '收到的商品规格与订单不符,贵店擅自更改合同内容。要求按订单规格重新发货或全额退款。', datetime('now', 'localtime'));

-- 个人信息泄露模板
INSERT INTO templates (problem_type_id, template_type, title, content, created_at) VALUES
(7, 'SLOGAN', '信息泄露基础话术', '贵店在未经我同意的情况下收集和使用我的个人信息,违反《个人信息保护法》。要求立即删除我的信息并说明用途。', datetime('now', 'localtime')),
(7, 'SLOGAN', '信息泄露骚扰话术', '自从在贵店购物后,频繁收到骚扰电话和短信,怀疑个人信息被泄露。要求调查信息泄露源头并承担相应责任。', datetime('now', 'localtime')),
(7, 'SLOGAN', '信息泄露过度话术', '贵店要求提供的个人信息超出必要范围,涉嫌过度收集。根据法律规定,要求说明收集目的并删除非必要信息。', datetime('now', 'localtime')),
(7, 'SLOGAN', '信息泄露举报话术', '贵店存在严重的个人信息泄露问题,我将向网信办和公安机关举报。请立即采取补救措施并赔偿损失。', datetime('now', 'localtime')),
(7, 'SLOGAN', '信息泄露删除话术', '我要求贵店立即删除我的所有个人信息,包括姓名、电话、地址等。如不配合,将通过法律途径维权。', datetime('now', 'localtime'));

-- 强制消费模板
INSERT INTO templates (problem_type_id, template_type, title, content, created_at) VALUES
(8, 'SLOGAN', '强制消费捆绑话术', '贵店强制捆绑销售,不购买搭售商品就无法下单。这违反《消费者权益保护法》,要求取消捆绑并单独销售。', datetime('now', 'localtime')),
(8, 'SLOGAN', '强制消费会员话术', '贵店强制要求开通会员才能购买商品,这属于强制消费。要求取消会员限制或退还会员费。', datetime('now', 'localtime')),
(8, 'SLOGAN', '强制消费诱导话术', '贵店通过虚假优惠信息诱导消费,实际并无优惠。这种欺骗行为严重侵害消费者权益,要求退款并道歉。', datetime('now', 'localtime')),
(8, 'SLOGAN', '强制消费加价话术', '贵店在结算时强制加价或收取额外费用,事先未明确告知。要求退还额外费用并说明收费依据。', datetime('now', 'localtime')),
(8, 'SLOGAN', '强制消费套餐话术', '贵店强制要求购买套餐,不允许单独购买所需商品。这限制了消费者的选择权,要求提供单品购买选项。', datetime('now', 'localtime'));

-- 霸王条款模板
INSERT INTO templates (problem_type_id, template_type, title, content, created_at) VALUES
(9, 'SLOGAN', '霸王条款免责话术', '贵店的服务条款中存在免除自身责任的霸王条款,根据《合同法》,此类条款无效。要求修改不公平条款。', datetime('now', 'localtime')),
(9, 'SLOGAN', '霸王条款解释话术', '贵店单方面解释合同条款,损害消费者权益。根据法律规定,格式条款应作出不利于提供方的解释。', datetime('now', 'localtime')),
(9, 'SLOGAN', '霸王条款退款话术', '贵店规定"一经售出概不退换",这属于典型霸王条款。根据《消费者权益保护法》,我有权七日无理由退货。', datetime('now', 'localtime')),
(9, 'SLOGAN', '霸王条款管辖话术', '贵店合同中约定的管辖法院明显不利于消费者,属于霸王条款。我有权选择对我有利的法院提起诉讼。', datetime('now', 'localtime')),
(9, 'SLOGAN', '霸王条款修改话术', '贵店保留随时修改条款的权利且不通知用户,这严重侵害消费者权益。要求提前通知并允许用户选择是否接受。', datetime('now', 'localtime'));

-- 食品安全模板
INSERT INTO templates (problem_type_id, template_type, title, content, created_at) VALUES
(10, 'SLOGAN', '食品安全过期话术', '收到的食品已过期,严重威胁健康安全。根据《食品安全法》,我要求退一赔十。请立即处理并召回同批次产品。', datetime('now', 'localtime')),
(10, 'SLOGAN', '食品安全标签话术', '食品标签信息不全,缺少生产日期、保质期等必要信息,违反《食品安全法》。要求退款并向监管部门举报。', datetime('now', 'localtime')),
(10, 'SLOGAN', '食品安全卫生话术', '食品包装破损,存在卫生安全隐患。贵店作为销售方应确保食品安全,要求退款并赔偿。', datetime('now', 'localtime')),
(10, 'SLOGAN', '食品安全异物话术', '食品中发现异物,严重影响食用安全。这属于重大食品安全问题,要求十倍赔偿并向市场监管部门投诉。', datetime('now', 'localtime')),
(10, 'SLOGAN', '食品安全虚假话术', '食品宣传为"有机""绿色"但无相关认证,属于虚假宣传。要求提供认证证明或退款并赔偿。', datetime('now', 'localtime'));

-- 创建测试卡密批次
INSERT INTO key_batch (prefix, count, expire_days, created_by, created_at) VALUES
('TEST', 20, 30, 1, datetime('now', 'localtime'));

-- 插入测试卡密（20个不同状态）
-- 使用固定的测试卡密，便于测试
-- Server Secret: test-secret-key-for-development-only

-- 10个可用卡密（TEST00000001 ~ TEST00000010）
INSERT INTO license_key (batch_id, key_hash, key_encrypted, status, expire_at, created_at) VALUES
(1, 'b06def747037c204e6f99422941bc4692f4bdf1617ed205dff42f37ddf58cca1', 'XtWCxs7eOHyfX4Kd0SDr63BJ2du3Kpx8TdCyCsOi7Vo=', 'active', datetime('now', '+30 days', 'localtime'), datetime('now', 'localtime')),
(1, '987d38f97ef52c6cd7f95e5e792a47c5ee8b229a549d2d1232f158c5eaba3599', '4V4L6Y5EO1Wk5CJ/HzVxcge2vH4+QEoSO6/ERDyw2jg=', 'active', datetime('now', '+30 days', 'localtime'), datetime('now', 'localtime')),
(1, '557838c1d9d0b80231636203ccf2318dc2f1dfb5f17f3d8519549a0dc6b121c9', 'NH1X5PNji+VU23lTGMxtcxD1br5ri+utXP0pDli4c5c=', 'active', datetime('now', '+30 days', 'localtime'), datetime('now', 'localtime')),
(1, '49804d109f748c37176ea0d1707e7fffc94f91cb8f1f0e02f6feb013ed9c468c', 'G0+kQ/6wY1KwWR/O4x3mE7gfkwOYREeCFx/q25xZs/Q=', 'active', datetime('now', '+30 days', 'localtime'), datetime('now', 'localtime')),
(1, '5d334c16f028fa9f927488aaa0ae2ce9cb1fa35f6c2a88ba4402e3f69671e639', 'Ne9vpyLtF+8lCLE0Dhb3WVj3YsvvmD/DqkFqJsFqZAI=', 'active', datetime('now', '+30 days', 'localtime'), datetime('now', 'localtime')),
(1, 'c46a5577caae0217dcc31b67e5a8c8f4c2d4460b266431c0d4ab5fb08d927169', 'Wt5YTljsMUKTM64zm1XK1fPt7v7I0TKO10OrQxE9AoU=', 'active', datetime('now', '+30 days', 'localtime'), datetime('now', 'localtime')),
(1, '3142b3dbe9654ca02d7b7026345f4ff9a70003060447e8cb57a61291335e256b', 'yKBqDeRtqbtUfdoJYNAVOmPzvN/tlegGyfGbqlcbJ/U=', 'active', datetime('now', '+30 days', 'localtime'), datetime('now', 'localtime')),
(1, '0a1abc0e54e983c61322de95c2f0581034dc5359e9e1a8745ebfcfca04d6d623', 'AuiEJVjd8asH3yrmUFWIl/gS9uNGJyluLM84HyvD0YI=', 'active', datetime('now', '+30 days', 'localtime'), datetime('now', 'localtime')),
(1, 'd5fb5ef031022d186372e273cb506db26ddc99a569c0f4b21a0b05ba9cf18e6d', 'aBYPXasZLgz+uyN1LQuPP0WD5CFlyHJ05mR4O0qst54=', 'active', datetime('now', '+30 days', 'localtime'), datetime('now', 'localtime')),
(1, '46f56a00a349346b399af63259b8735f9bd5b411232e9aedcd707688689f8c1d', 'ymsPwHnT3l+U6EmUNREKb5vjKAwNGVobwmlBGgvJPKw=', 'active', datetime('now', '+30 days', 'localtime'), datetime('now', 'localtime'));

-- 5个已使用卡密
INSERT INTO license_key (batch_id, key_hash, key_encrypted, status, expire_at, created_at, first_used_at, last_used_at, nickname) VALUES
(1, 'fcdaeb40da91f995fe04a4fb7f6e92bb2b9787856ae27963930318169397e9e3', '6Tk0hxkiaG8QlQzqM++8ROa8YIvvI4L76VPohXlsyxQ=', 'active', datetime('now', '+30 days', 'localtime'), datetime('now', '-10 days', 'localtime'), datetime('now', '-5 days', 'localtime'), datetime('now', '-1 hours', 'localtime'), '测试用户1'),
(1, '5b84d55937db13c61d67c96abc135b5feb500dc70e6e58709f0d4cebb0b7fc6e', 'k54+pbImueUlOsj89rVqrQ+7lBXDhjEo+JWUILRw7JQ=', 'active', datetime('now', '+30 days', 'localtime'), datetime('now', '-8 days', 'localtime'), datetime('now', '-3 days', 'localtime'), datetime('now', '-2 hours', 'localtime'), '测试用户2'),
(1, 'bf3291618adba076cd6be8b591db2ea792d0dd42c2515795b6773ce26d2e1bfa', 'k4L15p7ykCUPKrZvN82YWDQhgS1XR8gyWmAQyZabXvs=', 'active', datetime('now', '+30 days', 'localtime'), datetime('now', '-6 days', 'localtime'), datetime('now', '-2 days', 'localtime'), datetime('now', '-3 hours', 'localtime'), '测试用户3'),
(1, 'cc9f7b2132ca8da23f3d9efb9c55742095ead0fab81a90b1c0ce8bb13ed820bf', 'ROdw+2TrGIgHzUMd2+kueohTZX6VJr0RRfNfeLbXFrQ=', 'active', datetime('now', '+30 days', 'localtime'), datetime('now', '-4 days', 'localtime'), datetime('now', '-1 days', 'localtime'), datetime('now', '-4 hours', 'localtime'), '测试用户4'),
(1, '923749c9b0bb53fe2da2a2d2d2ab50f4805f7aabf2a59448baa4e3b67f541c5a', '2Pg21KP0hsfBFAdDxdEGv/TvMuvpqnTGvuVY50rdm64=', 'active', datetime('now', '+30 days', 'localtime'), datetime('now', '-2 days', 'localtime'), datetime('now', '-12 hours', 'localtime'), datetime('now', '-30 minutes', 'localtime'), '测试用户5');

-- 3个封禁卡密
INSERT INTO license_key (batch_id, key_hash, key_encrypted, status, expire_at, created_at, first_used_at, last_used_at) VALUES
(1, '61bade67ead932ed51892caf45f5057c11400399e9e39cb82bfdcb08b86f4d6a', 'G2hyvGzr07Dyc9EUj66bkhzbHJvN8Cw5jttXwmySLGI=', 'banned', datetime('now', '+30 days', 'localtime'), datetime('now', '-15 days', 'localtime'), datetime('now', '-10 days', 'localtime'), datetime('now', '-5 days', 'localtime')),
(1, '3be36f9cb6538a6a9ea139a8dab89af71326c3bb38b8cbf65441b16e9176575b', 'XWY8geKCKJmv+Sz8Q1bI5gav0SwPDHl5gEPrTiLToNA=', 'banned', datetime('now', '+30 days', 'localtime'), datetime('now', '-12 days', 'localtime'), datetime('now', '-8 days', 'localtime'), datetime('now', '-3 days', 'localtime')),
(1, 'b7756476e38a397e6b9cca705f3c3cd624f38353383f1f0a7a17ddb4b0e8ea10', 'zvnbT3fiRuYz4CGHUm8AvqPUnxIIIckHgztiVjlbzZM=', 'banned', datetime('now', '+30 days', 'localtime'), datetime('now', '-9 days', 'localtime'), datetime('now', '-6 days', 'localtime'), datetime('now', '-2 days', 'localtime'));

-- 2个过期卡密
INSERT INTO license_key (batch_id, key_hash, key_encrypted, status, expire_at, created_at, first_used_at, last_used_at) VALUES
(1, 'f6ff08492ca5f555993769b6fa0a1d19c29f62621a69e9951ed0e6e248f9f8b0', 'ifcVqfWnZ7vueb2oZtrjDzn0CtAw2/BSZmQeRYuKbn4=', 'active', datetime('now', '-5 days', 'localtime'), datetime('now', '-40 days', 'localtime'), datetime('now', '-35 days', 'localtime'), datetime('now', '-6 days', 'localtime')),
(1, '38be98916802128752a9bf21114c841024034b7ebc2eb6c63563e7492233f55f', 'HvVeZrS9hQwWIpYmoMqfC1N9/9+yHIgwB8FCAiIrA90=', 'active', datetime('now', '-3 days', 'localtime'), datetime('now', '-38 days', 'localtime'), datetime('now', '-33 days', 'localtime'), datetime('now', '-4 days', 'localtime'));

-- 插入示例使用日志
INSERT INTO key_usage_log (key_id, action, ip, user_agent, result, reason, created_at) VALUES
(11, 'verify', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'success', '卡密验证成功', datetime('now', '-5 days', 'localtime')),
(11, 'ping', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'success', '心跳检测正常', datetime('now', '-5 days', '+30 minutes', 'localtime')),
(11, 'access', '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'success', '访问话术生成功能', datetime('now', '-5 days', '+1 hours', 'localtime')),
(12, 'verify', '192.168.1.101', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'success', '卡密验证成功', datetime('now', '-3 days', 'localtime')),
(12, 'ping', '192.168.1.101', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'success', '心跳检测正常', datetime('now', '-3 days', '+45 minutes', 'localtime')),
(16, 'verify', '192.168.1.102', 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)', 'failed', '卡密已被封禁', datetime('now', '-5 days', 'localtime')),
(19, 'verify', '192.168.1.103', 'Mozilla/5.0 (Linux; Android 10)', 'failed', '卡密已过期', datetime('now', '-1 days', 'localtime')),
(1, 'verify', '192.168.1.104', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'failed', '卡密格式不正确', datetime('now', '-2 hours', 'localtime')),
(2, 'verify', '192.168.1.105', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'failed', '卡密无效', datetime('now', '-1 hours', 'localtime'));

-- 插入管理员操作日志
INSERT INTO admin_op_log (admin_id, action, details, ip, created_at) VALUES
(1, 'login', '管理员登录系统', '127.0.0.1', datetime('now', '-1 days', 'localtime')),
(1, 'generate_keys', '生成20个测试卡密，前缀：TEST，有效期：30天', '127.0.0.1', datetime('now', '-1 days', '+10 minutes', 'localtime')),
(1, 'ban_keys', '封禁3个违规卡密', '127.0.0.1', datetime('now', '-12 hours', 'localtime')),
(1, 'view_logs', '查看卡密使用日志', '127.0.0.1', datetime('now', '-6 hours', 'localtime')),
(1, 'export_keys', '导出批次1的卡密列表', '127.0.0.1', datetime('now', '-3 hours', 'localtime'));
