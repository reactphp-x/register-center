# 测试说明

本目录包含了针对 ReactPHP Register Center 项目的单元测试和集成测试。

## 测试文件说明

### 现有测试

1. **RegisterTest.php** - 测试 Register 类的核心功能
   - 服务器启动和停止
   - 连接管理
   - 日志记录
   - 错误处理

2. **MasterTest.php** - 测试 Master 类的核心功能
   - 连接和重连机制
   - 错误处理
   - 事件监听

### 新增测试

3. **ExamplesIntegrationTest.php** - 基于 examples 目录的集成测试
   - 测试 `examples/register.php` 示例的核心功能
   - 测试 `examples/register1.php` 示例的功能
   - 测试 `examples/master.php` 示例的服务注册和连接功能
   - 测试注册中心间的通信功能

4. **ServiceRegistryTest.php** - 测试 ServiceRegistry 类的功能
   - 服务注册和注销
   - 服务方法执行
   - 参数传递
   - 错误处理
   - 复杂对象服务测试

## 测试覆盖的 Examples 功能

### register.php 示例测试
- ✅ 注册中心启动
- ✅ Master 连接
- ✅ 服务调用执行 (`sayHello`, `sayHello2`)
- ✅ 注册中心间通信 (`register` 和 `remove` 命令)
- ✅ 服务状态获取

### register1.php 示例测试
- ✅ 第二个注册中心独立运行
- ✅ 服务状态获取 (`getServicesMaster`)
- ✅ 服务调用

### master.php 示例测试
- ✅ 服务注册 (`ServiceRegistry::register`)
- ✅ 连接处理和认证
- ✅ 错误和关闭事件处理
- ✅ 命令监听 (`register` 和 `remove`)
- ✅ 重连机制

## 运行测试

### 运行所有测试
```bash
vendor/bin/phpunit
# 输出: OK (27 tests, 118 assertions)
```

### 运行特定测试文件
```bash
# 运行集成测试 (5 个测试)
vendor/bin/phpunit tests/ExamplesIntegrationTest.php

# 运行 ServiceRegistry 测试 (11 个测试)
vendor/bin/phpunit tests/ServiceRegistryTest.php

# 运行现有的单元测试
vendor/bin/phpunit tests/RegisterTest.php  # 5 个测试
vendor/bin/phpunit tests/MasterTest.php    # 6 个测试
```

### 运行特定测试方法
```bash
# 测试 register.php 示例基础功能
vendor/bin/phpunit --filter testRegisterExampleBasicFunctionality

# 测试服务注册功能
vendor/bin/phpunit --filter testServiceRegistration

# 测试 Master 配置
vendor/bin/phpunit --filter testMasterExampleConfiguration

# 测试原始消息发送
vendor/bin/phpunit --filter testRegisterCenterRawMessageSending
```

## 测试特点

### 集成测试特点
- 使用随机端口避免冲突
- 模拟真实的 Master-Register 通信
- 测试异步事件处理
- 验证实际的数据传输

### 单元测试特点
- 隔离测试各个功能模块
- 使用 Mock 对象避免外部依赖
- 测试边界条件和错误情况
- 快速执行，适合频繁运行

## 测试数据和配置

### 测试端口范围
- Register 端口1: 20000-30000
- Register 端口2: 30001-40000

### 测试服务
- `hello-wrold`: 主要测试服务，包含 `sayHello()` 和 `sayHello2($name)` 方法
- `data-processor`: 数据处理测试服务
- `data-store`: 复杂对象测试服务

### 认证配置
- 测试使用的认证 token: `register-center-token-2024`

## 注意事项

1. **异步测试**: 集成测试中使用了 `waitForCondition` 方法来处理异步操作
2. **资源清理**: 每个测试结束后都会适当清理网络连接和事件循环
3. **端口冲突**: 使用随机端口来避免测试间的端口冲突
4. **状态隔离**: ServiceRegistry 测试会保存和恢复服务状态，确保测试间的隔离

## 扩展测试

如果需要添加更多基于 examples 的测试：

1. 在 `ExamplesIntegrationTest.php` 中添加新的测试方法
2. 确保测试方法名以 `test` 开头
3. 使用 `waitForCondition` 处理异步操作
4. 适当清理测试资源
5. 添加详细的断言和错误消息 