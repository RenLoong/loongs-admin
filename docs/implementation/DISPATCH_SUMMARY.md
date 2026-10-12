# 任务分发汇总（模块方案 · 建议值）

- 日期：2026-10-12（Asia/Shanghai）
- 主持：LoongsProject
- 约束：对齐已拍板 P0；**仍不并行写业务代码**；下表「建议」待你拍板后才进 DECISIONS 正式项
- 已拍板 P0：见 `DECISIONS.md` D-P0-1…5

---

## 1. 各模块交付一览

| 模块 | 交付要点 | 落盘/出处 |
|------|----------|-----------|
| **ERP** | standalone 最小能力；Mall/WMS 契约；建单时机配置枚举；Phase2 切片 E2-S0…S6 | 对话归档；可落 `ERP_PHASE2_PLAN.md`（待你明示） |
| **Mall** | standalone vs cluster；`mall.erp_order_create_timing`；模拟支付/预占补偿检查表 | `MALL_P0_DESIGN.md` |
| **WMS** | 配置矩阵；Reserve/Release/ShipConfirm 契约；后装九步对账 | 对话归档 |
| **CRM** | 客户字段×写入方；赢单转单 RPC；ServiceCase 推迟表述 | 对话归档 → 建议写入 D-P2 |
| **SCM** | 供应商权威矩阵；ASN 映射+幂等；Phase3 验收 10 步 | 对话归档 → D-P2-3/4 |
| **MES** | Phase4 首刀验收；MES↔WMS 契约；D-MES-1…8 | `MES_PHASE4_P0_DRAFT.md` |
| **HRM** | 首版 A/B 范围；Uuims 一对一+L0–L4；未实现合规清单 | 对话归档 → D-P3 |

---

## 2. 跨模块已对齐（可直接当工程约束）

1. **独立部署 + 后装对账**（已拍板）：稳定业务键 + ExternalReference；冲突人工核对。
2. **跨系统仅 RPC/投影**，禁止共享业务表。
3. **幂等**：普遍采用 `source + doc + line + action + request_id`（或等价 hash）；超时重试返回首次结果。
4. **库存写路径**：仅 WMS（cluster）；预占≠扣实物；出库确认同事务写流水。
5. **支付**：不以浏览器成功页为凭证；模拟支付须标注（D-P1-1 仍待确认）。

---

## 3. 建议你下一步拍板的项（非 P0）

### 优先（影响 Phase 2）
| ID | 建议默认 | 来源 |
|----|----------|------|
| D-P1-1 模拟支付 | Phase2 接受「明确标注的模拟支付」 | Mall |
| 建单时机产品默认 | `on_payment` / `on_payment_confirmed`（配置仍可改） | Mall/ERP |
| ERP Phase2 计划落盘 | 是否写 `ERP_PHASE2_PLAN.md` | ERP |

### Phase 3
| ID | 建议 | 来源 |
|----|------|------|
| D-P2-1 客户字段表 | 采用 CRM A 表；未赢单默认不预建 ERP 空客户 | CRM |
| D-P2-2 赢单默认单据 | 默认报价，订单需额外权限 | CRM |
| D-P2-3 供应商矩阵+冻结 | 采用 SCM A；冻结禁新 ASN/交期，不自动取消已下达 PO | SCM |
| D-P2-4 预期收货创建方 | 默认 ERP 按 PO 建 WMS 预期，ASN 补充物流 | SCM |
| ServiceCase | 首版推迟（CRM C） | CRM |

### Phase 4
| ID | 建议 | 来源 |
|----|------|------|
| D-P3-1 制造类型 | 默认离散 | MES |
| D-P3-2 薪酬 | 默认 A（不含薪酬） | HRM |
| D-P3-3 映射 | 一对一 | HRM |
| D-MES-1…8 | 见 MES 草案 | MES |

---

## 4. 推荐执行顺序（仍禁止七模块同时写码）

1. **最小 App 样板**（DemoBiz 或 Erp 空壳）— Phase 0 收尾  
2. Phase 1 共享约定  
3. Phase 2：Mall ↔ WMS ↔ ERP 闭环（按配置建单）  
4. Phase 3：SCM ASN + CRM 赢单  
5. Phase 4：MES / HRM  

---

## 5. 会议纪律

- 七 Bot 已下达文档任务并已回复；**未授权写码**。  
- 模块自建的 `*_DRAFT.md` / `MALL_P0_DESIGN.md` 视为建议稿，以你拍板后的 `DECISIONS.md` 为准。
