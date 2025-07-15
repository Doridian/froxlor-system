--local DOMAIN_CONFIGS = {}
--local DEFAULT_CONFIG = nil

local print = print
local sni_name = nil

local SNI_NAME_PREFIX = 'sni_name:'
local SNI_NAME_PREFIX_LEN = SNI_NAME_PREFIX:len()

local function set_cert(cfg)
    print('action:strict')
    print('cert_file:' .. cfg[1])
    print('key_file:' .. cfg[2])
    print('end')
end

local function set_default_cert()
    if DEFAULT_CONFIG then
        return set_cert(DEFAULT_CONFIG)
    end

    print('action:default')
    print('end')
end

local function handle_sni(name)
    local cfg = DOMAIN_CONFIGS[name]
    if cfg then
        return set_cert(cfg)
    end

    return set_default_cert()
end

local line
while true do
    line = io.read('*l')
    if line == 'end' then
        break
    end
    if line:sub(1, SNI_NAME_PREFIX_LEN) == SNI_NAME_PREFIX then
        sni_name = line:sub(SNI_NAME_PREFIX_LEN+1)
    end
end

if not sni_name then
    set_default_cert()
else
    handle_sni(sni_name)
end
os.exit(0)
