import Select from 'react-select';

const selectStyles = {
    control: (base, state) => ({
        ...base,
        minHeight: state.selectProps.compact ? 44 : 48,
        borderColor: state.isFocused ? '#143e2c' : '#d6d3d1',
        borderRadius: 8,
        backgroundColor: state.selectProps.backgroundColor,
        boxShadow: state.isFocused ? '0 0 0 1px #143e2c' : 'none',
        '&:hover': { borderColor: '#143e2c' },
    }),
    input: (base) => ({ ...base, color: '#1c1917' }),
    singleValue: (base) => ({ ...base, color: '#1c1917' }),
    placeholder: (base) => ({ ...base, color: '#57534e' }),
    menu: (base) => ({ ...base, zIndex: 60 }),
    option: (base, state) => ({
        ...base,
        color: state.isSelected ? '#ffffff' : '#1c1917',
        backgroundColor: state.isSelected ? '#143e2c' : state.isFocused ? '#eaf7ec' : '#ffffff',
        '&:active': { backgroundColor: '#c0edd3' },
    }),
};

export default function SearchableSelect({ id, options, value, onChange, placeholder, compact = false, backgroundColor = '#fafaf9' }) {
    return (
        <Select
            inputId={id}
            instanceId={id}
            options={options}
            value={options.find((option) => option.value === value) ?? null}
            onChange={(option) => onChange(option?.value ?? '')}
            placeholder={placeholder}
            isClearable
            isSearchable
            menuPlacement="auto"
            menuPosition="fixed"
            maxMenuHeight={240}
            noOptionsMessage={() => 'Tidak ada pilihan yang cocok'}
            styles={selectStyles}
            compact={compact}
            backgroundColor={backgroundColor}
        />
    );
}
